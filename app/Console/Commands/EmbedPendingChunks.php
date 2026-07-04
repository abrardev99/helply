<?php

namespace App\Console\Commands;

use App\Jobs\EmbedChunksJob;
use App\Models\Chunk;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('chunks:embed-pending')]
#[Description('Dispatch an embedding job for every bot that has chunks without embeddings.')]
class EmbedPendingChunks extends Command
{
    /**
     * Execute the console command.
     *
     * Keys the work off the Bot (one EmbedChunksJob per bot) so a bot's pending chunks are
     * embedded together in batched API calls. The job only touches NULL embeddings, so a
     * tick that overlaps an in-flight run is harmless.
     */
    public function handle(): int
    {
        $botIds = Chunk::query()
            ->whereNull('embedding')
            ->distinct()
            ->pluck('bot_id');

        if ($botIds->isEmpty()) {
            $this->info(__('No chunks awaiting embedding.'));

            return self::SUCCESS;
        }

        foreach ($botIds as $botId) {
            $this->info(__('Dispatching embedding for bot :id...', ['id' => $botId]));
            EmbedChunksJob::dispatch($botId);
        }

        $this->comment(__('Dispatched :count embedding job(s).', ['count' => $botIds->count()]));

        return self::SUCCESS;
    }
}
