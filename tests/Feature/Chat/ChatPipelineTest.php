<?php

use App\Ai\Agents\GroundingChecker;
use App\Ai\Agents\QueryTriage;
use App\Ai\Agents\SupportAgent;
use App\Data\RetrievalHit;
use App\Data\RetrievalResult;
use App\Enums\ChatOutcome;
use App\Models\Agent;
use App\Models\Chunk;
use App\Models\Document;
use App\Models\Team;
use App\Services\Chat\ChatPipeline;
use App\Services\Chat\Guardrail;
use Laravel\Ai\Embeddings;
use Laravel\Ai\Reranking;
use Laravel\Ai\Responses\Data\RankedDocument;

function chatAgent(): Agent
{
    $team = Team::factory()->create(['openai_api_key' => 'sk-valid-key']);
    $agent = Agent::factory()->for($team)->create([
        'name' => 'Acme Docs',
        'confidence_threshold' => 0.75,
        'chat_model' => 'gpt-5.4',
    ]);
    $document = Document::factory()->for($agent)->create();
    Chunk::factory()->for($agent)->for($document)->count(2)->create(['embedding' => array_fill(0, 1536, 0.1)]);

    return $agent;
}

function pipeline(): ChatPipeline
{
    return app(ChatPipeline::class);
}

it('refuses off-topic questions at the relevance gate without calling the chat model', function () {
    Embeddings::fake();
    QueryTriage::fake([['needs_lookup' => true, 'reason' => 'a question']]);
    Reranking::fake([[new RankedDocument(index: 0, document: 'x', score: 0.10)]]);
    SupportAgent::fake(['should never run']);

    $agent = chatAgent();

    $result = pipeline()->handle($agent, 'Help me write Python code');

    expect($result->outcome)->toBe(ChatOutcome::RefusedLowRelevance)
        ->and($result->flagged)->toBeTrue()
        ->and($result->answer)->toContain("couldn't find anything about that")
        ->and($result->sources)->toBe([]);

    SupportAgent::assertNeverPrompted();
});

it('answers on-topic questions that pass both guardrails', function () {
    Embeddings::fake();
    QueryTriage::fake([['needs_lookup' => true, 'reason' => 'a question']]);
    Reranking::fake([[new RankedDocument(index: 0, document: 'x', score: 0.95)]]);
    SupportAgent::fake(['Refunds are processed within 5 business days. [1]']);
    GroundingChecker::fake([['grounded' => true, 'reason' => 'supported']]);

    $agent = chatAgent();

    $result = pipeline()->handle($agent, 'How long do refunds take?');

    expect($result->outcome)->toBe(ChatOutcome::Answered)
        ->and($result->flagged)->toBeFalse()
        ->and($result->answer)->toBe('Refunds are processed within 5 business days. [1]')
        ->and($result->sources)->not->toBe([]);
});

it('greets small talk without retrieving or calling the chat model', function () {
    Embeddings::fake();
    QueryTriage::fake([['needs_lookup' => false, 'reason' => 'a greeting']]);
    SupportAgent::fake(['should never run']);

    $agent = chatAgent();

    $result = pipeline()->handle($agent, 'hi');

    // A greeting is a successful exchange, not something the agent failed to answer.
    expect($result->outcome)->toBe(ChatOutcome::Greeted)
        ->and($result->outcome->isRefusal())->toBeFalse()
        ->and($result->flagged)->toBeFalse()
        ->and($result->answer)->toContain('What would you like to know?')
        ->and($result->sources)->toBe([]);

    SupportAgent::assertNeverPrompted();
});

it('judges an un-reranked cosine score against the vector-only floor, not the agent threshold', function () {
    $agent = chatAgent(); // confidence_threshold = 0.75
    $guardrail = app(Guardrail::class);
    $hit = new RetrievalHit(chunkId: 'c', documentId: 'd', content: 'x', score: 0.50);

    // 0.50 is a strong cosine match but far below a reranker-tuned 0.75. Judging it by
    // the agent threshold would refuse every question whenever reranking is unavailable.
    expect($guardrail->passesRelevanceGate(new RetrievalResult([$hit], 0.50, reranked: false), $agent))->toBeTrue()
        ->and($guardrail->passesRelevanceGate(new RetrievalResult([$hit], 0.50, reranked: true), $agent))->toBeFalse();

    // Clearly off-topic cosine scores still fail.
    expect($guardrail->passesRelevanceGate(new RetrievalResult([$hit], 0.08, reranked: false), $agent))->toBeFalse()
        ->and($guardrail->passesRelevanceGate(new RetrievalResult([], 0.0, reranked: false), $agent))->toBeFalse();
});

it('refuses when the answer fails the grounding check', function () {
    Embeddings::fake();
    QueryTriage::fake([['needs_lookup' => true, 'reason' => 'a question']]);
    Reranking::fake([[new RankedDocument(index: 0, document: 'x', score: 0.95)]]);
    SupportAgent::fake(['The CEO is Taylor Swift and refunds take 3 seconds.']);
    GroundingChecker::fake([['grounded' => false, 'reason' => 'not supported by context']]);

    $agent = chatAgent();

    $result = pipeline()->handle($agent, 'Who is the CEO?');

    expect($result->outcome)->toBe(ChatOutcome::RefusedUngrounded)
        ->and($result->flagged)->toBeTrue()
        ->and($result->answer)->toContain("couldn't find anything about that");
});
