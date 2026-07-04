<?php

use App\Enums\BotStatus;
use App\Enums\TeamRole;
use App\Models\Bot;
use App\Models\Chunk;
use App\Models\Conversation;
use App\Models\Document;
use App\Models\Message;
use App\Models\Team;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Create a user that is a member of a fresh (non-personal) team with the given role,
 * and switch their current team to it.
 *
 * @return array{0: User, 1: Team}
 */
function memberOfTeam(TeamRole $role): array
{
    $user = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($user, ['role' => $role->value]);
    $user->switchTeam($team);

    return [$user, $team];
}

it('lists only the current team\'s bots', function () {
    [$user, $team] = memberOfTeam(TeamRole::Owner);

    $ownBot = Bot::factory()->for($team)->create();
    $otherBot = Bot::factory()->create();

    $this->actingAs($user)
        ->get(route('bots.index', ['current_team' => $team->slug]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('bots/index')
            ->has('bots', 1)
            ->where('bots.0.id', $ownBot->id)
            ->where('permissions.canManageBots', true),
        );

    expect($otherBot->team_id)->not->toBe($team->id);
});

it('lets an owner create a bot', function () {
    [$user, $team] = memberOfTeam(TeamRole::Owner);

    $this->actingAs($user)
        ->post(route('bots.store', ['current_team' => $team->slug]), [
            'name' => 'Support bot',
            'embed_origins' => ['https://example.com'],
        ])
        ->assertRedirect();

    $bot = Bot::query()->firstOrFail();

    expect($bot->name)->toBe('Support bot')
        ->and($bot->team_id)->toBe($team->id)
        ->and($bot->embed_origins)->toBe(['https://example.com'])
        ->and($bot->status)->toBe(BotStatus::Active);
});

it('lets an admin rename a bot and edit origins', function () {
    [$user, $team] = memberOfTeam(TeamRole::Admin);
    $bot = Bot::factory()->for($team)->create(['name' => 'Old name']);

    $this->actingAs($user)
        ->put(route('bots.update', ['current_team' => $team->slug, 'bot' => $bot->id]), [
            'name' => 'New name',
            'embed_origins' => ['https://app.example.com'],
            'status' => BotStatus::Paused->value,
        ])
        ->assertRedirect();

    $bot->refresh();

    expect($bot->name)->toBe('New name')
        ->and($bot->embed_origins)->toBe(['https://app.example.com'])
        ->and($bot->status)->toBe(BotStatus::Paused);
});

it('deletes a bot and cascades its documents, chunks, conversations and messages', function () {
    [$user, $team] = memberOfTeam(TeamRole::Owner);
    $bot = Bot::factory()->for($team)->create();

    $document = Document::factory()->for($bot)->create();
    $chunk = Chunk::factory()->for($bot)->for($document)->create();
    $conversation = Conversation::factory()->for($bot)->create();
    $message = Message::factory()->for($conversation)->create();

    $this->actingAs($user)
        ->delete(route('bots.destroy', ['current_team' => $team->slug, 'bot' => $bot->id]))
        ->assertRedirect(route('bots.index', ['current_team' => $team->slug]));

    expect(Bot::find($bot->id))->toBeNull()
        ->and(Document::find($document->id))->toBeNull()
        ->and(Chunk::find($chunk->id))->toBeNull()
        ->and(Conversation::find($conversation->id))->toBeNull()
        ->and(Message::find($message->id))->toBeNull();
});

it('forbids members from creating, updating, or deleting bots', function () {
    [$user, $team] = memberOfTeam(TeamRole::Member);
    $bot = Bot::factory()->for($team)->create();

    $this->actingAs($user)
        ->post(route('bots.store', ['current_team' => $team->slug]), ['name' => 'Nope'])
        ->assertForbidden();

    $this->actingAs($user)
        ->put(route('bots.update', ['current_team' => $team->slug, 'bot' => $bot->id]), ['name' => 'Nope'])
        ->assertForbidden();

    $this->actingAs($user)
        ->delete(route('bots.destroy', ['current_team' => $team->slug, 'bot' => $bot->id]))
        ->assertForbidden();
});

it('lets members view the bot list and detail', function () {
    [$user, $team] = memberOfTeam(TeamRole::Member);
    $bot = Bot::factory()->for($team)->create();

    $this->actingAs($user)
        ->get(route('bots.index', ['current_team' => $team->slug]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('permissions.canManageBots', false),
        );

    $this->actingAs($user)
        ->get(route('bots.show', ['current_team' => $team->slug, 'bot' => $bot->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('bots/show'));
});

it('blocks access to another team\'s prefix', function () {
    [$user] = memberOfTeam(TeamRole::Owner);
    $otherTeam = Team::factory()->create();

    $this->actingAs($user)
        ->get(route('bots.index', ['current_team' => $otherTeam->slug]))
        ->assertForbidden();
});

it('cannot resolve a bot from another team via its own prefix', function () {
    [$user, $team] = memberOfTeam(TeamRole::Owner);
    $otherBot = Bot::factory()->create();

    $this->actingAs($user)
        ->get(route('bots.show', ['current_team' => $team->slug, 'bot' => $otherBot->id]))
        ->assertNotFound();
});

it('validates the bot name and origins', function () {
    [$user, $team] = memberOfTeam(TeamRole::Owner);

    $this->actingAs($user)
        ->post(route('bots.store', ['current_team' => $team->slug]), [
            'name' => '',
            'embed_origins' => ['https://example.com/with/path'],
        ])
        ->assertInvalid(['name', 'embed_origins.0']);
});
