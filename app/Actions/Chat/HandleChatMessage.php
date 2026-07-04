<?php

namespace App\Actions\Chat;

use App\Data\WidgetChatResponse;
use App\Enums\MessageRole;
use App\Models\Agent;
use App\Models\Message;
use App\Services\Chat\ChatPipeline;
use Illuminate\Support\Str;

class HandleChatMessage
{
    public function __construct(private ChatPipeline $pipeline)
    {
        //
    }

    /**
     * Orchestrate a single visitor turn: persist the question, run the guarded RAG
     * pipeline, persist the assistant reply (with sources, retrieval score, flagged), and
     * return the visitor-facing response.
     */
    public function handle(Agent $agent, ?string $sessionId, string $message): WidgetChatResponse
    {
        $sessionId = $sessionId ?: (string) Str::uuid();

        $conversation = $agent->conversations()->firstOrCreate(['session_id' => $sessionId]);

        // Prior turns, captured before this question is stored, for follow-up context.
        $history = $conversation->messages()
            ->oldest()
            ->get()
            ->map(fn (Message $message): array => [
                'role' => $message->role->value,
                'content' => $message->content,
            ])
            ->all();

        $conversation->messages()->create([
            'role' => MessageRole::User,
            'content' => $message,
        ]);

        $result = $this->pipeline->handle($agent, $message, $history);

        $conversation->messages()->create([
            'role' => MessageRole::Assistant,
            'content' => $result->answer,
            'sources' => $result->sources,
            'retrieval_score' => $result->retrievalScore,
            'flagged' => $result->flagged,
        ]);

        return new WidgetChatResponse(
            answer: $result->answer,
            sources: $result->sources,
            conversationId: $conversation->id,
        );
    }
}
