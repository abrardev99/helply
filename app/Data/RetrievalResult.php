<?php

namespace App\Data;

class RetrievalResult
{
    /**
     * @param  list<RetrievalHit>  $hits
     */
    public function __construct(
        public array $hits,
        public float $topScore,
        public bool $reranked,
    ) {}

    /**
     * An empty result — nothing relevant was found. Feeds guardrail #1 (F10).
     */
    public static function empty(): self
    {
        return new self(hits: [], topScore: 0.0, reranked: false);
    }
}
