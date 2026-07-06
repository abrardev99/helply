<?php

namespace App\Services\Ingestion;

class TextChunker
{
    /**
     * Target maximum chunk size in characters. At a ~4-chars-per-token heuristic this is
     * roughly 750 tokens, near the top of the 500–800 token target range.
     */
    private const MaxChars = 3000;

    /**
     * How much of the previous chunk's tail to repeat at the start of the next chunk, so
     * context that straddles a boundary is not lost. ~13% of MaxChars.
     */
    private const OverlapChars = 400;

    /**
     * Split document text into retrieval-sized, slightly overlapping chunks, preferring
     * sentence boundaries so chunks are not cut mid-sentence where avoidable.
     *
     * @return list<string>
     */
    public function chunk(string $text): array
    {
        $text = trim((string) preg_replace('/\s+/', ' ', $text));

        if ($text === '') {
            return [];
        }

        if (strlen($text) <= self::MaxChars) {
            return [$text];
        }

        $chunks = [];
        $current = '';

        foreach ($this->segments($text) as $segment) {
            $candidate = $current === '' ? $segment : $current.' '.$segment;

            if (strlen($candidate) <= self::MaxChars) {
                $current = $candidate;

                continue;
            }

            // The segment does not fit: flush the current chunk and start a new one that
            // begins with an overlap of the previous chunk's tail (dropped if that would
            // itself overflow, keeping every chunk within MaxChars).
            if ($current !== '') {
                $chunks[] = $current;

                $current = trim($this->tail($current).' '.$segment);

                if (strlen($current) > self::MaxChars) {
                    $current = $segment;
                }

                continue;
            }

            $current = $segment;
        }

        if (trim($current) !== '') {
            $chunks[] = trim($current);
        }

        return $chunks;
    }

    /**
     * Break text into sentence segments, hard-splitting any single sentence that is longer
     * than MaxChars on word boundaries.
     *
     * @return list<string>
     */
    private function segments(string $text): array
    {
        $sentences = preg_split('/(?<=[.!?])\s+/', $text) ?: [$text];

        $segments = [];

        foreach ($sentences as $sentence) {
            if (strlen($sentence) <= self::MaxChars) {
                $segments[] = $sentence;

                continue;
            }

            foreach ($this->splitLongSentence($sentence) as $piece) {
                $segments[] = $piece;
            }
        }

        return $segments;
    }

    /**
     * Split an over-long sentence into word-bounded pieces no larger than MaxChars.
     *
     * @return list<string>
     */
    private function splitLongSentence(string $sentence): array
    {
        $pieces = [];
        $buffer = '';

        foreach (explode(' ', $sentence) as $word) {
            $candidate = $buffer === '' ? $word : $buffer.' '.$word;

            if (strlen($candidate) > self::MaxChars && $buffer !== '') {
                $pieces[] = $buffer;
                $buffer = $word;

                continue;
            }

            $buffer = $candidate;
        }

        if ($buffer !== '') {
            $pieces[] = $buffer;
        }

        return $pieces;
    }

    /**
     * The last OverlapChars of a chunk, trimmed forward to the next word boundary so the
     * overlap does not start with a partial word.
     */
    private function tail(string $chunk): string
    {
        if (strlen($chunk) <= self::OverlapChars) {
            return $chunk;
        }

        $tail = substr($chunk, -self::OverlapChars);
        $spacePosition = strpos($tail, ' ');

        return $spacePosition === false ? $tail : substr($tail, $spacePosition + 1);
    }
}
