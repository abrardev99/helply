<?php

namespace App\Actions\Chat;

use App\Data\WidgetChatResponse;
use App\Enums\MessageRole;
use App\Models\Agent;
use App\Models\Message;
use App\Services\Chat\ChatPipeline;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

class HandleChatMessage
{
    public function __construct(private ChatPipeline $pipeline) {}

    /**
     * Orchestrate a single visitor turn: persist the question, run the guarded RAG
     * pipeline, persist the assistant reply (with sources, retrieval score, flagged), and
     * return the visitor-facing response.
     *
     * The session is resumed only from a server-signed token: a client cannot supply a raw
     * id to attach to (or read) another visitor's conversation. A missing or forged token
     * starts a fresh session, and a new signed token is returned for the widget to echo.
     */
    public function handle(Agent $agent, ?string $sessionToken, string $message): WidgetChatResponse
    {
        $sessionId = $this->resolveSessionId($sessionToken);

        $conversation = $agent->conversations()->firstOrCreate(['session_id' => $sessionId]);

        // Prior turns, captured before this question is stored, for follow-up context.
        $history = array_values($conversation->messages()
            ->oldest()
            ->get()
            ->map(fn (Message $message): array => [
                'role' => $message->role->value,
                'content' => $message->content,
            ])
            ->all());

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
            sessionToken: Crypt::encryptString($sessionId),
        );
    }

    /**
     * Decrypt the client's signed session token into its conversation id, falling back to a
     * fresh id when the token is absent or fails signature verification.
     */
    private function resolveSessionId(?string $sessionToken): string
    {
        if (blank($sessionToken)) {
            return (string) Str::uuid();
        }

        try {
            return Crypt::decryptString($sessionToken);
        } catch (DecryptException) {
            return (string) Str::uuid();
        }
    }
}
