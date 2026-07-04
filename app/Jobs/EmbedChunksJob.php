<?php

namespace App\Jobs;

use App\Ai\Exceptions\MissingOpenAiKeyException;
use App\Ai\Support\ResolvesTenantKey;
use App\Models\Bot;
use App\Models\Chunk;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Embeddings;
use Laravel\Ai\Enums\Lab;

class EmbedChunksJob implements ShouldQueue
{
    use Queueable;

    /**
     * The number of times the job may be attempted. Transient provider errors (429/5xx)
     * are retried with backoff; a missing key is handled non-fatally without a retry.
     */
    public int $tries = 3;

    /**
     * How many chunk texts to send to the embeddings API per call.
     */
    private const BATCH_SIZE = 96;

    /**
     * @param  string  $botId  The bot whose chunks should be embedded.
     * @param  bool  $force  When true, clear existing embeddings and recompute them all.
     */
    public function __construct(
        public string $botId,
        public bool $force = false,
    ) {}

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @return list<int>
     */
    public function backoff(): array
    {
        return [10, 30, 60];
    }

    /**
     * Execute the job.
     */
    public function handle(ResolvesTenantKey $keys): void
    {
        $bot = Bot::query()->find($this->botId);

        if ($bot === null) {
            return;
        }

        if ($this->force) {
            $bot->chunks()->update(['embedding' => null, 'embedded_at' => null]);
        }

        try {
            // The tenant key is applied to the AI SDK only for this job's scope.
            $keys->withTenantKey($bot, fn () => $this->embedPending($bot));
        } catch (MissingOpenAiKeyException) {
            // Non-fatal: leave chunks un-embedded so a later run (once a key is set)
            // completes them. Never log key material.
            Log::warning('Skipped embedding: no OpenAI key configured.', ['bot_id' => $bot->id]);
        }
    }

    /**
     * Embed every chunk of the bot that has no embedding yet, in batches. Only NULL
     * embeddings are touched, so re-running is a no-op for already-embedded chunks.
     */
    private function embedPending(Bot $bot): void
    {
        $pending = $bot->chunks()
            ->whereNull('embedding')
            ->orderBy('id')
            ->get();

        foreach ($pending->chunk(self::BATCH_SIZE) as $batch) {
            $this->embedBatch($bot, $batch->values());
        }
    }

    /**
     * @param  Collection<int, Chunk>  $batch
     */
    private function embedBatch(Bot $bot, Collection $batch): void
    {
        $response = Embeddings::for($batch->pluck('content')->all())
            ->dimensions(1536)
            ->generate(Lab::OpenAI, $bot->embedding_model);

        foreach ($batch as $index => $chunk) {
            $chunk->embedding = $response->embeddings[$index];
            $chunk->embedded_at = now();
            $chunk->save();
        }
    }
}
