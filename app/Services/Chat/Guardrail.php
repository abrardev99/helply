<?php

namespace App\Services\Chat;

use App\Ai\Agents\GroundingChecker;
use App\Ai\Support\ResolvesTenantKey;
use App\Data\RetrievalResult;
use App\Models\Bot;
use Illuminate\Support\Str;
use Laravel\Ai\Enums\Lab;

class Guardrail
{
    /**
     * Phrases that indicate the model itself declined to answer. Such a reply is not a
     * hallucination, so it is safe to pass through without the LLM grounding check.
     */
    private const REFUSAL_MARKERS = [
        "don't have that information",
        'do not have that information',
        "can't help",
        'cannot help',
        "couldn't find",
        'could not find',
    ];

    public function __construct(private ResolvesTenantKey $keys)
    {
        //
    }

    /**
     * Guardrail #1 — relevance gate (no LLM call). Passes only when the retrieval found
     * usable chunks whose top score clears the bot's confidence threshold.
     */
    public function passesRelevanceGate(RetrievalResult $retrieval, Bot $bot): bool
    {
        return $retrieval->hits !== [] && $retrieval->topScore >= $bot->confidence_threshold;
    }

    /**
     * Guardrail #2 — grounding check. Cheap heuristics first, then a structured-output
     * agent that decides whether the answer is fully supported by the retrieved context.
     */
    public function answerIsGrounded(Bot $bot, string $answer, RetrievalResult $retrieval): bool
    {
        $answer = trim($answer);

        if ($answer === '' || $retrieval->hits === []) {
            return false;
        }

        if ($this->looksLikeRefusal($answer)) {
            return true;
        }

        $verdict = $this->keys->withTenantKey($bot, fn () => (new GroundingChecker)
            ->prompt($this->groundingPrompt($answer, $retrieval), provider: Lab::OpenAI, model: $bot->chat_model));

        return (bool) ($verdict['grounded'] ?? false);
    }

    /**
     * Friendly, translatable refusal shown when a guardrail blocks an answer.
     */
    public function refusalMessage(Bot $bot): string
    {
        return __("I can only help with questions about :bot, and I couldn't find anything about that in our content.", [
            'bot' => $bot->name,
        ]);
    }

    private function looksLikeRefusal(string $answer): bool
    {
        return Str::contains(Str::lower($answer), self::REFUSAL_MARKERS);
    }

    private function groundingPrompt(string $answer, RetrievalResult $retrieval): string
    {
        $context = collect($retrieval->hits)
            ->map(fn ($hit, int $i): string => '['.($i + 1).'] '.$hit->content)
            ->implode("\n");

        return "CONTEXT:\n{$context}\n\nANSWER:\n{$answer}";
    }
}
