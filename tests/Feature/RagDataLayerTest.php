<?php

use App\Enums\DocumentStatus;
use App\Models\Bot;
use App\Models\Chunk;
use App\Models\Conversation;
use App\Models\Document;
use App\Models\Message;
use App\Models\Team;

it('generates uuid primary keys for rag entities', function () {
    $bot = Bot::factory()->create();

    expect($bot->getKeyName())->toBe('id')
        ->and(Str::isUuid($bot->id))->toBeTrue();
});

it('scopes a bot to an existing integer-keyed team', function () {
    $team = Team::factory()->create();
    $bot = Bot::factory()->for($team)->create();

    expect($bot->team)->toBeInstanceOf(Team::class)
        ->and($bot->team->is($team))->toBeTrue()
        ->and($team->bots->first()->is($bot))->toBeTrue();
});

it('wires the bot document and chunk relationships', function () {
    $chunk = Chunk::factory()->create();

    expect($chunk->document)->toBeInstanceOf(Document::class)
        ->and($chunk->bot)->toBeInstanceOf(Bot::class)
        ->and($chunk->bot_id)->toBe($chunk->document->bot_id)
        ->and($chunk->document->bot->is($chunk->bot))->toBeTrue()
        ->and($chunk->document->chunks->first()->is($chunk))->toBeTrue();
});

it('wires the conversation and message relationships', function () {
    $conversation = Conversation::factory()->create();
    $message = Message::factory()->for($conversation)->create();

    expect($conversation->bot)->toBeInstanceOf(Bot::class)
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
    $bot = Bot::factory()->create(['embed_origins' => ['https://a.test', 'https://b.test']]);
    $message = Message::factory()->create([
        'sources' => ['https://source.test'],
        'retrieval_score' => 0.42,
        'flagged' => true,
    ]);

    expect($bot->embed_origins)->toBeArray()->toHaveCount(2)
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

it('cascade deletes children when a bot is deleted', function () {
    $chunk = Chunk::factory()->create();
    $bot = $chunk->bot;
    Conversation::factory()->for($bot)->create();

    $bot->delete();

    expect(Document::count())->toBe(0)
        ->and(Chunk::count())->toBe(0)
        ->and(Conversation::count())->toBe(0);
});
