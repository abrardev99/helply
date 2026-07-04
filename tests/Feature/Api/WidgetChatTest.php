<?php

use App\Ai\Agents\GroundingChecker;
use App\Ai\Agents\SupportAgent;
use App\Models\Bot;
use App\Models\Chunk;
use App\Models\Conversation;
use App\Models\Document;
use App\Models\Message;
use App\Models\Team;
use Laravel\Ai\Embeddings;
use Laravel\Ai\Reranking;
use Laravel\Ai\Responses\Data\RankedDocument;

const ALLOWED_ORIGIN = 'https://shop.example.com';

function widgetBot(): Bot
{
    $team = Team::factory()->create(['openai_api_key' => 'sk-valid-key']);
    $bot = Bot::factory()->for($team)->create([
        'name' => 'Acme Support',
        'embed_origins' => [ALLOWED_ORIGIN],
        'confidence_threshold' => 0.75,
    ]);
    $document = Document::factory()->for($bot)->create();
    Chunk::factory()->for($bot)->for($document)->count(2)->create(['embedding' => array_fill(0, 1536, 0.1)]);

    return $bot;
}

function fakeAnsweringPipeline(): void
{
    Embeddings::fake();
    Reranking::fake([[new RankedDocument(index: 0, document: 'x', score: 0.95)]]);
    SupportAgent::fake(['Refunds are processed within 5 business days. [1]']);
    GroundingChecker::fake([['grounded' => true, 'reason' => 'supported']]);
}

function chatUrl(Bot $bot): string
{
    return '/api/widget/'.$bot->id.'/chat';
}

it('answers and persists both messages under one conversation', function () {
    fakeAnsweringPipeline();
    $bot = widgetBot();

    $response = $this->postJson(chatUrl($bot), [
        'session_id' => 'sess-123',
        'message' => 'How long do refunds take?',
    ], ['Origin' => ALLOWED_ORIGIN]);

    $response->assertOk()
        ->assertJson([
            'answer' => 'Refunds are processed within 5 business days. [1]',
        ])
        ->assertJsonStructure(['answer', 'sources', 'conversation_id']);

    $conversation = Conversation::query()->firstOrFail();
    expect($conversation->bot_id)->toBe($bot->id)
        ->and($conversation->session_id)->toBe('sess-123')
        ->and($conversation->messages()->count())->toBe(2);

    $assistant = $conversation->messages()->where('role', 'assistant')->firstOrFail();
    expect($assistant->flagged)->toBeFalse()
        ->and($assistant->retrieval_score)->toBe(0.95);

    // No secrets leak into the response body.
    expect($response->getContent())->not->toContain('sk-valid-key');
});

it('rejects requests from a non-allow-listed origin', function () {
    fakeAnsweringPipeline();
    $bot = widgetBot();

    $this->postJson(chatUrl($bot), ['message' => 'hi'], ['Origin' => 'https://evil.example.com'])
        ->assertForbidden();

    $this->postJson(chatUrl($bot), ['message' => 'hi'])
        ->assertForbidden();

    expect(Message::query()->count())->toBe(0);
});

it('validates the message body', function () {
    fakeAnsweringPipeline();
    $bot = widgetBot();

    $this->postJson(chatUrl($bot), ['message' => ''], ['Origin' => ALLOWED_ORIGIN])
        ->assertStatus(422)
        ->assertJsonValidationErrors('message');

    $this->postJson(chatUrl($bot), ['message' => str_repeat('a', 2001)], ['Origin' => ALLOWED_ORIGIN])
        ->assertStatus(422);
});

it('reuses the conversation across turns with the same session id', function () {
    fakeAnsweringPipeline();
    $bot = widgetBot();

    foreach (['First question?', 'Follow up?'] as $message) {
        $this->postJson(chatUrl($bot), ['session_id' => 'sess-abc', 'message' => $message], ['Origin' => ALLOWED_ORIGIN])
            ->assertOk();
    }

    expect(Conversation::query()->count())->toBe(1)
        ->and(Conversation::query()->firstOrFail()->messages()->count())->toBe(4);
});

it('throttles floods with a 429', function () {
    // Low relevance keeps each request cheap (no chat-model call) while still counting
    // against the limiter.
    Embeddings::fake();
    Reranking::fake([[new RankedDocument(index: 0, document: 'x', score: 0.05)]]);
    $bot = widgetBot();

    $statuses = [];

    for ($i = 0; $i < 32; $i++) {
        $statuses[] = $this->postJson(chatUrl($bot), ['message' => "q{$i}"], ['Origin' => ALLOWED_ORIGIN])->status();
    }

    expect($statuses[0])->toBe(200)
        ->and($statuses)->toContain(429);
});
