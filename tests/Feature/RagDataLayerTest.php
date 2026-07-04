<?php

use App\Enums\DocumentStatus;
use App\Models\Agent;
use App\Models\Chunk;
use App\Models\Conversation;
use App\Models\Document;
use App\Models\Message;
use App\Models\Team;

it('generates uuid primary keys for rag entities', function () {
    $agent = Agent::factory()->create();

    expect($agent->getKeyName())->toBe('id')
        ->and(Str::isUuid($agent->id))->toBeTrue();
});

it('scopes a agent to an existing integer-keyed team', function () {
    $team = Team::factory()->create();
    $agent = Agent::factory()->for($team)->create();

    expect($agent->team)->toBeInstanceOf(Team::class)
        ->and($agent->team->is($team))->toBeTrue()
        ->and($team->agents->first()->is($agent))->toBeTrue();
});

it('wires the agent document and chunk relationships', function () {
    $chunk = Chunk::factory()->create();

    expect($chunk->document)->toBeInstanceOf(Document::class)
        ->and($chunk->agent)->toBeInstanceOf(Agent::class)
        ->and($chunk->agent_id)->toBe($chunk->document->agent_id)
        ->and($chunk->document->agent->is($chunk->agent))->toBeTrue()
        ->and($chunk->document->chunks->first()->is($chunk))->toBeTrue();
});

it('wires the conversation and message relationships', function () {
    $conversation = Conversation::factory()->create();
    $message = Message::factory()->for($conversation)->create();

    expect($conversation->agent)->toBeInstanceOf(Agent::class)
        ->and($message->conversation->is($conversation))->toBeTrue()
        ->and($conversation->messages->first()->is($message))->toBeTrue();
});

it('casts the document status to the DocumentStatus enum', function () {
    $document = Document::factory()->create(['status' => DocumentStatus::Done]);

    expect($document->status)->toBe(DocumentStatus::Done)
        ->and($document->fresh()->status)->toBe(DocumentStatus::Done)
        ->and($document->status->label())->toBe('Done');
});

it('casts json, float, and boolean attributes', function () {
    $agent = Agent::factory()->create(['embed_origins' => ['https://a.test', 'https://b.test']]);
    $message = Message::factory()->create([
        'sources' => ['https://source.test'],
        'retrieval_score' => 0.42,
        'flagged' => true,
    ]);

    expect($agent->embed_origins)->toBeArray()->toHaveCount(2)
        ->and($message->sources)->toBeArray()
        ->and($message->retrieval_score)->toBeFloat()
        ->and($message->flagged)->toBeTrue();
});

it('persists and reads back a 1536-dimension pgvector embedding', function () {
    $chunk = Chunk::factory()->create();

    $fresh = $chunk->fresh();

    expect($fresh->embedding)->toBeArray()->toHaveCount(1536)
        ->and($fresh->embedding[0])->toBeFloat();
})->skip(
    fn () => DB::connection()->getDriverName() !== 'pgsql',
    'Vector columns require a PostgreSQL connection.'
);

it('cascade deletes children when a agent is deleted', function () {
    $chunk = Chunk::factory()->create();
    $agent = $chunk->agent;
    Conversation::factory()->for($agent)->create();

    $agent->delete();

    expect(Document::count())->toBe(0)
        ->and(Chunk::count())->toBe(0)
        ->and(Conversation::count())->toBe(0);
});
