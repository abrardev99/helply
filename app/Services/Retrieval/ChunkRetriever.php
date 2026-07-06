<?php

namespace App\Services\Retrieval;

use App\Ai\Support\ResolvesTenantKey;
use App\Data\RetrievalHit;
use App\Data\RetrievalResult;
use App\Models\Agent;
use App\Models\Chunk;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Embeddings;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Reranking;
use Laravel\Ai\Responses\Data\RankedDocument;

class ChunkRetriever
{
    public function __construct(private ResolvesTenantKey $keys) {}

    /**
     * Retrieve the most relevant chunks for a question in two stages: a wide pgvector
     * similarity search (high recall) followed by a rerank (high precision) that collapses
     * the candidates to the top-N. Reranking degrades gracefully to vector order when no
     * rerank provider key is configured.
     */
    public function retrieve(Agent $agent, string $question, int $candidates = 25, int $topN = 6): RetrievalResult
    {
        $question = trim($question);

        if ($question === '') {
            return RetrievalResult::empty();
        }

        $queryEmbedding = $this->keys->withTenantKey($agent, fn (): array => Embeddings::for([$question])
            ->dimensions(1536)
            ->generate(Lab::OpenAI, $agent->embedding_model)
            ->embeddings[0]);

        // TENANT SAFETY: agent_id is filtered first and always — chunks never cross tenants.
        $candidateChunks = Chunk::query()
            ->select('chunks.*')
            ->selectVectorDistance('embedding', $queryEmbedding, 'distance')
            ->where('agent_id', $agent->id)
            ->whereNotNull('embedding')
            ->orderByVectorDistance('embedding', $queryEmbedding)
            ->limit($candidates)
            ->get();

        if ($candidateChunks->isEmpty()) {
            return RetrievalResult::empty();
        }

        if ($this->canRerank()) {
            return $this->rerankedResult($question, $candidateChunks, $topN);
        }

        return $this->vectorOnlyResult($candidateChunks, $topN);
    }

    /**
     * Whether a rerank provider is available: either the SDK is faked (tests) or a
     * platform-level rerank key is configured.
     */
    private function canRerank(): bool
    {
        if (Reranking::isFaked()) {
            return true;
        }

        $provider = config('ai.default_for_reranking');

        return filled(config("ai.providers.{$provider}.key"));
    }

    /**
     * @param  Collection<int, Chunk>  $candidates
     */
    private function rerankedResult(string $question, Collection $candidates, int $topN): RetrievalResult
    {
        $ordered = $candidates->values();

        $response = Reranking::of($ordered->pluck('content')->all())
            ->limit($topN)
            ->rerank($question);

        $hits = array_values(array_map(
            fn (RankedDocument $result): RetrievalHit => $this->hit($ordered[$result->index], $result->score),
            $response->results,
        ));

        return new RetrievalResult($hits, $hits === [] ? 0.0 : $hits[0]->score, reranked: true);
    }

    /**
     * @param  Collection<int, Chunk>  $candidates
     */
    private function vectorOnlyResult(Collection $candidates, int $topN): RetrievalResult
    {
        Log::info('Retrieval reranking skipped: no rerank provider key configured.');

        $hits = array_values($candidates
            ->take($topN)
            ->map(fn (Chunk $chunk): RetrievalHit => $this->hit($chunk, $this->similarity($chunk)))
            ->all());

        return new RetrievalResult($hits, $hits === [] ? 0.0 : $hits[0]->score, reranked: false);
    }

    private function hit(Chunk $chunk, float $score): RetrievalHit
    {
        return new RetrievalHit(
            chunkId: $chunk->id,
            documentId: $chunk->document_id,
            content: $chunk->content,
            score: $score,
        );
    }

    /**
     * Convert the pgvector cosine distance (0 = identical) exposed as the `distance`
     * attribute into a cosine similarity score.
     */
    private function similarity(Chunk $chunk): float
    {
        return 1.0 - (float) $chunk->getAttribute('distance');
    }
}
