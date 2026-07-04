<?php

use App\Data\RetrievalHit;
use App\Data\RetrievalResult;
use App\Services\Retrieval\ContextPacker;

function hit(string $id, string $content): RetrievalHit
{
    return new RetrievalHit(chunkId: $id, documentId: 'doc-'.$id, content: $content, score: 0.9);
}

it('packs hits with sequential citation markers and aligned sources', function () {
    $result = new RetrievalResult([
        hit('a', 'Refunds take 5 days.'),
        hit('b', 'Shipping is free over $50.'),
    ], topScore: 0.9, reranked: true);

    $packed = (new ContextPacker)->pack($result);

    expect($packed->context)->toContain('[1] Refunds take 5 days.')
        ->and($packed->context)->toContain('[2] Shipping is free over $50.')
        ->and($packed->sources)->toHaveCount(2)
        ->and($packed->sources[0])->toBe(['marker' => 1, 'chunkId' => 'a', 'documentId' => 'doc-a'])
        ->and($packed->sources[1]['marker'])->toBe(2);
});

it('produces empty context and no sources for an empty result', function () {
    $packed = (new ContextPacker)->pack(RetrievalResult::empty());

    expect($packed->context)->toBe('')
        ->and($packed->sources)->toBe([]);
});

it('stops packing once the character budget is exceeded', function () {
    $big = str_repeat('x', 5000);
    $result = new RetrievalResult([
        hit('a', $big),
        hit('b', $big),
        hit('c', $big),
    ], topScore: 0.9, reranked: true);

    $packed = (new ContextPacker)->pack($result);

    // Only the first block fits before the ~8000-char budget is hit.
    expect(count($packed->sources))->toBeLessThan(3)
        ->and($packed->sources[0]['chunkId'])->toBe('a');
});
