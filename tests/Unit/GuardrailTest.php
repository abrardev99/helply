<?php

use App\Ai\Support\ResolvesTenantKey;
use App\Data\RetrievalHit;
use App\Data\RetrievalResult;
use App\Models\Agent;
use App\Services\Chat\Guardrail;

function guardrail(): Guardrail
{
    return new Guardrail(new ResolvesTenantKey);
}

function agentWithThreshold(float $threshold): Agent
{
    $agent = new Agent;
    $agent->confidence_threshold = $threshold;

    return $agent;
}

function resultWithScore(float $score): RetrievalResult
{
    return new RetrievalResult(
        [new RetrievalHit(chunkId: 'c', documentId: 'd', content: 'Some content.', score: $score)],
        topScore: $score,
        reranked: true,
    );
}

it('passes the relevance gate only when the top score clears the threshold', function () {
    $agent = agentWithThreshold(0.75);

    expect(guardrail()->passesRelevanceGate(resultWithScore(0.9), $agent))->toBeTrue()
        ->and(guardrail()->passesRelevanceGate(resultWithScore(0.5), $agent))->toBeFalse();
});

it('fails the relevance gate when there are no hits', function () {
    expect(guardrail()->passesRelevanceGate(RetrievalResult::empty(), agentWithThreshold(0.75)))->toBeFalse();
});

it('treats a model refusal as grounded without calling the checker', function () {
    $grounded = guardrail()->answerIsGrounded(
        agentWithThreshold(0.75),
        "I'm sorry, but I don't have that information in our content.",
        resultWithScore(0.9),
    );

    expect($grounded)->toBeTrue();
});

it('is not grounded for an empty answer or empty context', function () {
    expect(guardrail()->answerIsGrounded(agentWithThreshold(0.75), '', resultWithScore(0.9)))->toBeFalse()
        ->and(guardrail()->answerIsGrounded(agentWithThreshold(0.75), 'An answer.', RetrievalResult::empty()))->toBeFalse();
});
