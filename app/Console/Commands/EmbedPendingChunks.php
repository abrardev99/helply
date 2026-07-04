<?php

namespace App\Console\Commands;

use App\Jobs\EmbedChunksJob;
use App\Models\Chunk;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('chunks:embed-pending')]
#[Description('Dispatch an embedding job for every agent that has chunks without embeddings.')]
class EmbedPendingChunks extends Command
{
    /**
     * Execute the console command.
     *
     * Keys the work off the Agent (one EmbedChunksJob per agent) so a agent's pending chunks are
     * embedded together in batched API calls. The job only touches NULL embeddings, so a
     * tick that overlaps an in-flight run is harmless.
     */
    public function handle(): int
    {
        $agentIds = Chunk::query()
            ->whereNull('embedding')
            ->distinct()
            ->pluck('agent_id');

        if ($agentIds->isEmpty()) {
            $this->info(__('No chunks awaiting embedding.'));

            return self::SUCCESS;
        }

        foreach ($agentIds as $agentId) {
            $this->info(__('Dispatching embedding for agent :id...', ['id' => $agentId]));
            EmbedChunksJob::dispatch($agentId);
        }

        $this->comment(__('Dispatched :count embedding job(s).', ['count' => $agentIds->count()]));

        return self::SUCCESS;
    }
}
