<?php

namespace App\Services\Chat;

use App\Ai\AnswerGenerator;
use App\Data\ChatResult;
use App\Enums\ChatOutcome;
use App\Models\Bot;
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
    public function handle(Bot $bot, string $question, array $history = []): ChatResult
    {
        $retrieval = $this->retriever->retrieve($bot, $question);

        if (! $this->guardrail->passesRelevanceGate($retrieval, $bot)) {
            return $this->refusal($bot, ChatOutcome::RefusedLowRelevance, $retrieval->topScore);
        }

        $generated = $this->generator->generate($bot, $question, $retrieval, $history);

        if (! $this->guardrail->answerIsGrounded($bot, $generated->answer, $retrieval)) {
            return $this->refusal($bot, ChatOutcome::RefusedUngrounded, $retrieval->topScore);
        }

        return new ChatResult(
            answer: $generated->answer,
            outcome: ChatOutcome::Answered,
            sources: $generated->sources,
            retrievalScore: $retrieval->topScore,
            flagged: false,
        );
    }

    private function refusal(Bot $bot, ChatOutcome $outcome, float $score): ChatResult
    {
        return new ChatResult(
            answer: $this->guardrail->refusalMessage($bot),
            outcome: $outcome,
            sources: [],
            retrievalScore: $score,
            flagged: true,
        );
    }
}
