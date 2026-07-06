<?php

namespace App\Data;

use App\Enums\ChatOutcome;

class ChatResult
{
    /**
     * @param  list<array{marker: int, chunkId: string, documentId: string}>  $sources
     */
    public function __construct(
        public string $answer,
        public ChatOutcome $outcome,
        public array $sources,
        public float $retrievalScore,
        public bool $flagged,
    ) {}
}
