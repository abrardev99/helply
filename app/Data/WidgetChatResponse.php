<?php

namespace App\Data;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\JsonResponse;

class WidgetChatResponse implements Responsable
{
    /**
     * @param  list<array{marker: int, chunkId: string, documentId: string}>  $sources
     */
    public function __construct(
        public string $answer,
        public array $sources,
        public string $conversationId,
    ) {
        //
    }

    /**
     * Only the visitor-facing fields are serialized — never the OpenAI key or system prompt.
     */
    public function toResponse($request): JsonResponse
    {
        return response()->json([
            'answer' => $this->answer,
            'sources' => $this->sources,
            'conversation_id' => $this->conversationId,
        ]);
    }
}
