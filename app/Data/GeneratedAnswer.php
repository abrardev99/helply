<?php

namespace App\Data;

class GeneratedAnswer
{
    /**
     * @param  string  $answer  The model's grounded answer text.
     * @param  list<array{marker: int, chunkId: string, documentId: string}>  $sources
     */
    public function __construct(
        public string $answer,
        public array $sources,
    ) {
        //
    }
}
