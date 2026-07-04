<?php

use App\Models\Bot;
use App\Models\Chunk;
use App\Models\Document;
use App\Models\Team;
use App\Services\Retrieval\ChunkRetriever;
use Laravel\Ai\Embeddings;
use Laravel\Ai\Reranking;
use Laravel\Ai\Responses\Data\RankedDocument;

/**
 * @return array{0: Bot, 1: Document}
 */
function embeddedBot(int $chunkCount = 3): array
{
    $team = Team::factory()->create(['openai_api_key' => 'sk-valid-key']);
    $bot = Bot::factory()->for($team)->create(['embedding_model' => 'text-embedding-3-small']);
    $document = Document::factory()->for($bot)->create();

    Chunk::factory()->for($bot)->for($document)->count($chunkCount)->create([
        'embedding' => array_fill(0, 1536, 0.1),
    ]);

    return [$bot, $document];
}

function retriever(): ChunkRetriever
{
    return app(ChunkRetriever::class);
}

it('returns top-N chunks ordered by rerank score', function () {
    Embeddings::fake();
    Reranking::fake([[
        new RankedDocument(index: 2, document: 'c', score: 0.95),
        new RankedDocument(index: 0, document: 'a', score: 0.80),
        new RankedDocument(index: 1, document: 'b', score: 0.40),
    ]]);

    [$bot] = embeddedBot(chunkCount: 3);

    $result = retriever()->retrieve($bot, 'How do refunds work?', topN: 6);

    expect($result->reranked)->toBeTrue()
        ->and($result->topScore)->toBe(0.95)
        ->and($result->hits)->toHaveCount(3)
        ->and($result->hits[0]->score)->toBe(0.95)
        ->and($result->hits[1]->score)->toBe(0.80)
        ->and($result->hits[2]->score)->toBe(0.40);

    Reranking::assertReranked(fn ($prompt) => $prompt->limit === 6
        && $prompt->contains('refunds'));
});

it('only returns chunks belonging to the queried bot', function () {
    Embeddings::fake();
    Reranking::fake();

    [$botA] = embeddedBot(chunkCount: 3);
    [$botB] = embeddedBot(chunkCount: 3);

    $result = retriever()->retrieve($botA, 'anything');

    $botAChunkIds = $botA->chunks()->pluck('id')->all();

    foreach ($result->hits as $hit) {
        expect($botAChunkIds)->toContain($hit->chunkId);
    }
})->repeat(3);

it('falls back to vector order when no rerank provider key is configured', function () {
    Embeddings::fake();
    // Reranking is NOT faked and no key is set, so retrieval must degrade gracefully.

    [$bot] = embeddedBot(chunkCount: 4);

    $result = retriever()->retrieve($bot, 'a question', topN: 2);

    expect($result->reranked)->toBeFalse()
        ->and($result->hits)->toHaveCount(2)
        ->and($result->topScore)->toBeFloat();

    Reranking::assertNothingReranked();
});

it('returns an empty result with zero top score when nothing is embedded', function () {
    Embeddings::fake();
    Reranking::fake();

    $team = Team::factory()->create(['openai_api_key' => 'sk-valid-key']);
    $bot = Bot::factory()->for($team)->create();
    // Chunks exist but have no embedding yet.
    $document = Document::factory()->for($bot)->create();
    Chunk::factory()->for($bot)->for($document)->create(['embedding' => null]);

    $result = retriever()->retrieve($bot, 'a question');

    expect($result->hits)->toBe([])
        ->and($result->topScore)->toBe(0.0)
        ->and($result->reranked)->toBeFalse();
});
