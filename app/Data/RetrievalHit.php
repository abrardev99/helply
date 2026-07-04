<?php

namespace App\Data;

class RetrievalHit
{
    public function __construct(
        public string $chunkId,
        public string $documentId,
        public string $content,
        public float $score,
    ) {
        //
    }
}
