<?php

namespace App\Data;

class PackedContext
{
    /**
     * @param  string  $context  Citation-marked chunk text to feed the model.
     * @param  list<array{marker: int, chunkId: string, documentId: string}>  $sources
     */
    public function __construct(
        public string $context,
        public array $sources,
    ) {}
}
