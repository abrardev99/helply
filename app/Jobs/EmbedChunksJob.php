<?php

namespace App\Jobs;

use App\Ai\Exceptions\MissingOpenAiKeyException;
use App\Ai\Support\ResolvesTenantKey;
use App\Models\Agent;
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
    private const BatchSize = 96;

    /**
     * @param  string  $agentId  The agent whose chunks should be embedded.
     * @param  bool  $force  When true, clear existing embeddings and recompute them all.
     */
    public function __construct(
        public string $agentId,
        public bool $force = false,
    ) {}

    /** @return list<int> */
    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function handle(ResolvesTenantKey $keys): void
    {
        $agent = Agent::query()->find($this->agentId);

        if ($agent === null) {
            return;
        }

        if ($this->force) {
            $agent->chunks()->update(['embedding' => null, 'embedded_at' => null]);
        }

        try {
            // The tenant key is applied to the AI SDK only for this job's scope.
            $keys->withTenantKey($agent, fn () => $this->embedPending($agent));
        } catch (MissingOpenAiKeyException) {
            // Non-fatal: leave chunks un-embedded so a later run (once a key is set)
            // completes them. Never log key material.
            Log::warning('Skipped embedding: no OpenAI key configured.', ['agent_id' => $agent->id]);
        }
    }

    /**
     * Embed every chunk of the agent that has no embedding yet, in batches. Only NULL
     * embeddings are touched, so re-running is a no-op for already-embedded chunks.
     */
    private function embedPending(Agent $agent): void
    {
        $pending = $agent->chunks()
            ->whereNull('embedding')
            ->orderBy('id')
            ->get();

        foreach ($pending->chunk(self::BatchSize) as $batch) {
            $this->embedBatch($agent, $batch->values());
        }
    }

    /**
     * @param  Collection<int, Chunk>  $batch
     */
    private function embedBatch(Agent $agent, Collection $batch): void
    {
        $response = Embeddings::for($batch->pluck('content')->all())
            ->dimensions(1536)
            ->generate(Lab::OpenAI, $agent->embedding_model);

        foreach ($batch as $index => $chunk) {
            $chunk->embedding = $response->embeddings[$index];
            $chunk->embedded_at = now();
            $chunk->save();
        }
    }
}
