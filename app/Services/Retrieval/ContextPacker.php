<?php

namespace App\Services\Retrieval;

use App\Data\PackedContext;
use App\Data\RetrievalResult;

class ContextPacker
{
    /**
     * Character budget for the packed context. Roughly ~2k tokens, comfortably within a
     * chat request alongside the system prompt and history.
     */
    private const MAX_CHARS = 8000;

    /**
     * Turn retrieved hits into a single citation-marked context string plus an aligned
     * sources list, stopping once the character budget is reached.
     */
    public function pack(RetrievalResult $result): PackedContext
    {
        $context = '';
        $sources = [];
        $marker = 0;

        foreach ($result->hits as $hit) {
            $marker++;
            $block = "[{$marker}] {$hit->content}\n\n";

            if ($context !== '' && strlen($context) + strlen($block) > self::MAX_CHARS) {
                break;
            }

            $context .= $block;
            $sources[] = [
                'marker' => $marker,
                'chunkId' => $hit->chunkId,
                'documentId' => $hit->documentId,
            ];
        }

        return new PackedContext(trim($context), $sources);
    }
}
