<?php

namespace App\Services\Chat;

use App\Ai\Agents\GroundingChecker;
use App\Ai\Support\ResolvesTenantKey;
use App\Data\RetrievalResult;
use App\Models\Agent;
use Illuminate\Support\Str;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Responses\StructuredAgentResponse;

class Guardrail
{
    /**
     * Phrases that indicate the model itself declined to answer. Such a reply is not a
     * hallucination, so it is safe to pass through without the LLM grounding check.
     */
    private const RefusalMarkers = [
        "don't have that information",
        'do not have that information',
        "can't help",
        'cannot help',
        "couldn't find",
        'could not find',
    ];

    public function __construct(private ResolvesTenantKey $keys) {}

    /**
     * Floor applied when retrieval could not rerank, so the top score is a raw cosine
     * similarity rather than a reranker's relevance score. The two are on different
     * scales and are not interchangeable: against a real corpus, on-topic questions
     * score roughly 0.50–0.70 by cosine while clearly off-topic ones sit below 0.10, so
     * the agent's reranker-tuned threshold (0.75 by default) would refuse everything.
     */
    private const VectorOnlyRelevanceFloor = 0.35;

    /**
     * Guardrail #1 — relevance gate (no LLM call). Passes only when the retrieval found
     * usable chunks whose top score clears the threshold appropriate to how that score
     * was produced.
     */
    public function passesRelevanceGate(RetrievalResult $retrieval, Agent $agent): bool
    {
        if ($retrieval->hits === []) {
            return false;
        }

        $threshold = $retrieval->reranked
            ? $agent->confidence_threshold
            : self::VectorOnlyRelevanceFloor;

        return $retrieval->topScore >= $threshold;
    }

    /**
     * Guardrail #2 — grounding check. Cheap heuristics first, then a structured-output
     * agent that decides whether the answer is fully supported by the retrieved context.
     */
    public function answerIsGrounded(Agent $agent, string $answer, RetrievalResult $retrieval): bool
    {
        $answer = trim($answer);

        if ($answer === '' || $retrieval->hits === []) {
            return false;
        }

        if ($this->looksLikeRefusal($answer)) {
            return true;
        }

        $verdict = $this->keys->withTenantKey($agent, fn () => (new GroundingChecker)
            ->prompt($this->groundingPrompt($answer, $retrieval), provider: Lab::OpenAI, model: $agent->chat_model));

        if (! $verdict instanceof StructuredAgentResponse) {
            return false;
        }

        return (bool) ($verdict->structured['grounded'] ?? false);
    }

    /**
     * Friendly, translatable refusal shown when a guardrail blocks an answer.
     */
    public function refusalMessage(Agent $agent): string
    {
        return __("Sorry, I couldn't find anything about that in the content I have. I can only answer questions about this site — try asking me something it covers.");
    }

    /**
     * Friendly reply to small talk, which needs no documentation lookup.
     */
    public function greetingMessage(Agent $agent): string
    {
        return __('Hi! I can answer questions about this site. What would you like to know?');
    }

    /**
     * Friendly message shown when the agent is not usable (e.g. no OpenAI key configured).
     */
    public function unavailableMessage(Agent $agent): string
    {
        return __("Sorry, :agent isn't available right now. Please try again later.", [
            'agent' => $agent->name,
        ]);
    }

    private function looksLikeRefusal(string $answer): bool
    {
        return Str::contains(Str::lower($answer), self::RefusalMarkers);
    }

    private function groundingPrompt(string $answer, RetrievalResult $retrieval): string
    {
        $context = collect($retrieval->hits)
            ->map(fn ($hit, int $i): string => '['.($i + 1).'] '.$hit->content)
            ->implode("\n");

        return "CONTEXT:\n{$context}\n\nANSWER:\n{$answer}";
    }
}
