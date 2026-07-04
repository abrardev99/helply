<?php

namespace App\Services\Chat;

use App\Ai\AnswerGenerator;
use App\Data\ChatResult;
use App\Enums\ChatOutcome;
use App\Models\Agent;
use App\Services\Retrieval\ChunkRetriever;

class ChatPipeline
{
    public function __construct(
        private ChunkRetriever $retriever,
        private Guardrail $guardrail,
        private AnswerGenerator $generator,
    ) {
        //
    }

    /**
     * Answer a visitor question end-to-end with both guardrails:
     *
     *   retrieve → relevance gate (pre-LLM) → generate → grounding check (post-LLM)
     *
     * A failure at either gate short-circuits to a canned refusal. In particular, a failed
     * relevance gate never calls the (expensive) chat model.
     *
     * @param  list<array{role: string, content: string}>  $history
     */
    public function handle(Agent $agent, string $question, array $history = []): ChatResult
    {
        $retrieval = $this->retriever->retrieve($agent, $question);

        if (! $this->guardrail->passesRelevanceGate($retrieval, $agent)) {
            return $this->refusal($agent, ChatOutcome::RefusedLowRelevance, $retrieval->topScore);
        }

        $generated = $this->generator->generate($agent, $question, $retrieval, $history);

        if (! $this->guardrail->answerIsGrounded($agent, $generated->answer, $retrieval)) {
            return $this->refusal($agent, ChatOutcome::RefusedUngrounded, $retrieval->topScore);
        }

        return new ChatResult(
            answer: $generated->answer,
            outcome: ChatOutcome::Answered,
            sources: $generated->sources,
            retrievalScore: $retrieval->topScore,
            flagged: false,
        );
    }

    private function refusal(Agent $agent, ChatOutcome $outcome, float $score): ChatResult
    {
        return new ChatResult(
            answer: $this->guardrail->refusalMessage($agent),
            outcome: $outcome,
            sources: [],
            retrievalScore: $score,
            flagged: true,
        );
    }
}
