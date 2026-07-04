<?php

use App\Services\Ingestion\TextChunker;

const CHUNK_MAX_CHARS = 3000;

function chunker(): TextChunker
{
    return new TextChunker;
}

it('returns nothing for empty or whitespace-only text', function (string $input) {
    expect(chunker()->chunk($input))->toBe([]);
})->with(['', '   ', "\n\t  \n"]);

it('returns a single chunk for short text', function () {
    $chunks = chunker()->chunk('This is a short document. It has two sentences.');

    expect($chunks)->toHaveCount(1)
        ->and($chunks[0])->toBe('This is a short document. It has two sentences.');
});

it('splits long text into multiple chunks within the size limit', function () {
    $text = collect(range(1, 300))
        ->map(fn (int $n) => "This is sentence number {$n} in a fairly long document.")
        ->implode(' ');

    $chunks = chunker()->chunk($text);

    expect(count($chunks))->toBeGreaterThan(1);

    foreach ($chunks as $chunk) {
        expect(strlen($chunk))->toBeLessThanOrEqual(CHUNK_MAX_CHARS);
    }
});

it('overlaps consecutive chunks', function () {
    $text = collect(range(1, 300))
        ->map(fn (int $n) => "This is sentence number {$n} in a fairly long document.")
        ->implode(' ');

    $chunks = chunker()->chunk($text);

    // The start of each chunk after the first is carried over from the tail of the
    // previous chunk, so it must appear within it.
    $openingOfSecond = substr($chunks[1], 0, 40);

    expect($chunks[0])->toContain($openingOfSecond);
});

it('does not cut on a mid-sentence boundary when sentences are available', function () {
    $text = collect(range(1, 300))
        ->map(fn (int $n) => "This is sentence number {$n} in a fairly long document.")
        ->implode(' ');

    $chunks = chunker()->chunk($text);

    // Every chunk but the last should end on a sentence terminator.
    foreach (array_slice($chunks, 0, -1) as $chunk) {
        expect(rtrim($chunk))->toEndWith('.');
    }
});

it('hard-splits a single sentence longer than the limit', function () {
    $longWordRun = str_repeat('word ', 1000); // ~5000 chars, no sentence breaks
    $chunks = chunker()->chunk($longWordRun);

    expect(count($chunks))->toBeGreaterThan(1);

    foreach ($chunks as $chunk) {
        expect(strlen($chunk))->toBeLessThanOrEqual(CHUNK_MAX_CHARS);
    }
});
