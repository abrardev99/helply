<?php

use App\Ai\Support\ResolvesTenantKey;
use App\Enums\TeamRole;
use App\Jobs\EmbedChunksJob;
use App\Models\Agent;
use App\Models\Chunk;
use App\Models\Document;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Laravel\Ai\Embeddings;

function runEmbed(string $agentId, bool $force = false): void
{
    (new EmbedChunksJob($agentId, $force))->handle(app(ResolvesTenantKey::class));
}

/**
 * @return array{0: Agent, 1: Document}
 */
function agentWithKey(?string $key = 'sk-valid-key'): array
{
    $team = Team::factory()->create(['openai_api_key' => $key]);
    $agent = Agent::factory()->for($team)->create(['embedding_model' => 'text-embedding-3-small']);
    $document = Document::factory()->for($agent)->create();

    return [$agent, $document];
}

it('embeds all null-embedding chunks with 1536-dim vectors', function () {
    Embeddings::fake();
    [$agent, $document] = agentWithKey('sk-valid-key');
    Chunk::factory()->for($agent)->for($document)->count(3)->create(['embedding' => null, 'embedded_at' => null]);

    runEmbed($agent->id);

    $agent->chunks()->get()->each(function (Chunk $chunk) {
        expect($chunk->embedding)->toBeArray()
            ->and($chunk->embedding)->toHaveCount(1536)
            ->and($chunk->embedded_at)->not->toBeNull();
    });

    Embeddings::assertGenerated(fn ($prompt) => $prompt->dimensions === 1536);
});

it('only embeds chunks that have no embedding yet', function () {
    Embeddings::fake();
    [$agent, $document] = agentWithKey();

    $embedded = Chunk::factory()->for($agent)->for($document)->create([
        'embedding' => array_fill(0, 1536, 0.5),
        'embedded_at' => now()->subDay(),
    ]);
    $pending = Chunk::factory()->for($agent)->for($document)->create(['embedding' => null]);

    runEmbed($agent->id);

    // Exactly one API call, containing only the pending chunk's text.
    Embeddings::assertGenerated(fn ($prompt) => $prompt->count() === 1
        && $prompt->contains($pending->content));

    expect($pending->fresh()->embedding)->toHaveCount(1536)
        ->and($embedded->fresh()->embedding)->toEqual(array_fill(0, 1536, 0.5));
});

it('re-embeds every chunk on the force path', function () {
    Embeddings::fake();
    [$agent, $document] = agentWithKey();
    Chunk::factory()->for($agent)->for($document)->count(2)->create([
        'embedding' => array_fill(0, 1536, 0.1),
        'embedded_at' => now(),
    ]);

    runEmbed($agent->id, force: true);

    Embeddings::assertGenerated(fn ($prompt) => $prompt->count() === 2);
});

it('is a non-fatal no-op when no key is configured', function () {
    Embeddings::fake();
    [$agent, $document] = agentWithKey(key: null);
    $chunk = Chunk::factory()->for($agent)->for($document)->create(['embedding' => null]);

    runEmbed($agent->id);

    Embeddings::assertNothingGenerated();
    expect($chunk->fresh()->embedding)->toBeNull();
});

it('reconcile command dispatches an embedding job per agent with pending chunks', function () {
    Queue::fake();
    [$agent, $document] = agentWithKey();
    Chunk::factory()->for($agent)->for($document)->create(['embedding' => null]);

    $this->artisan('chunks:embed-pending')->assertSuccessful();

    Queue::assertPushed(EmbedChunksJob::class, fn (EmbedChunksJob $job) => $job->agentId === $agent->id);
});

it('lets a manager trigger a re-embed', function () {
    Queue::fake();

    $user = User::factory()->create();
    $team = Team::factory()->create(['openai_api_key' => 'sk-valid']);
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);
    $agent = Agent::factory()->for($team)->create();

    $this->actingAs($user)
        ->post(route('agents.reembed', ['current_team' => $team->slug, 'agent' => $agent->id]))
        ->assertRedirect();

    Queue::assertPushed(EmbedChunksJob::class, fn (EmbedChunksJob $job) => $job->agentId === $agent->id && $job->force === true);
});
