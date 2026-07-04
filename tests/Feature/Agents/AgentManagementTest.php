<?php

use App\Enums\AgentStatus;
use App\Enums\TeamRole;
use App\Models\Agent;
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

it('lists only the current team\'s agents', function () {
    [$user, $team] = memberOfTeam(TeamRole::Owner);

    $ownAgent = Agent::factory()->for($team)->create();
    $otherAgent = Agent::factory()->create();

    $this->actingAs($user)
        ->get(route('agents.index', ['current_team' => $team->slug]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('agents/index')
            ->has('agents', 1)
            ->where('agents.0.id', $ownAgent->id)
            ->where('permissions.canManageAgents', true),
        );

    expect($otherAgent->team_id)->not->toBe($team->id);
});

it('lets an owner create a agent', function () {
    [$user, $team] = memberOfTeam(TeamRole::Owner);

    $this->actingAs($user)
        ->post(route('agents.store', ['current_team' => $team->slug]), [
            'name' => 'Support agent',
            'embed_origins' => ['https://example.com'],
        ])
        ->assertRedirect();

    $agent = Agent::query()->firstOrFail();

    expect($agent->name)->toBe('Support agent')
        ->and($agent->team_id)->toBe($team->id)
        ->and($agent->embed_origins)->toBe(['https://example.com'])
        ->and($agent->status)->toBe(AgentStatus::Active);
});

it('lets an admin rename a agent and edit origins', function () {
    [$user, $team] = memberOfTeam(TeamRole::Admin);
    $agent = Agent::factory()->for($team)->create(['name' => 'Old name']);

    $this->actingAs($user)
        ->put(route('agents.update', ['current_team' => $team->slug, 'agent' => $agent->id]), [
            'name' => 'New name',
            'embed_origins' => ['https://app.example.com'],
            'status' => AgentStatus::Paused->value,
        ])
        ->assertRedirect();

    $agent->refresh();

    expect($agent->name)->toBe('New name')
        ->and($agent->embed_origins)->toBe(['https://app.example.com'])
        ->and($agent->status)->toBe(AgentStatus::Paused);
});

it('deletes a agent and cascades its documents, chunks, conversations and messages', function () {
    [$user, $team] = memberOfTeam(TeamRole::Owner);
    $agent = Agent::factory()->for($team)->create();

    $document = Document::factory()->for($agent)->create();
    $chunk = Chunk::factory()->for($agent)->for($document)->create();
    $conversation = Conversation::factory()->for($agent)->create();
    $message = Message::factory()->for($conversation)->create();

    $this->actingAs($user)
        ->delete(route('agents.destroy', ['current_team' => $team->slug, 'agent' => $agent->id]))
        ->assertRedirect(route('agents.index', ['current_team' => $team->slug]));

    expect(Agent::find($agent->id))->toBeNull()
        ->and(Document::find($document->id))->toBeNull()
        ->and(Chunk::find($chunk->id))->toBeNull()
        ->and(Conversation::find($conversation->id))->toBeNull()
        ->and(Message::find($message->id))->toBeNull();
});

it('forbids members from creating, updating, or deleting agents', function () {
    [$user, $team] = memberOfTeam(TeamRole::Member);
    $agent = Agent::factory()->for($team)->create();

    $this->actingAs($user)
        ->post(route('agents.store', ['current_team' => $team->slug]), ['name' => 'Nope'])
        ->assertForbidden();

    $this->actingAs($user)
        ->put(route('agents.update', ['current_team' => $team->slug, 'agent' => $agent->id]), ['name' => 'Nope'])
        ->assertForbidden();

    $this->actingAs($user)
        ->delete(route('agents.destroy', ['current_team' => $team->slug, 'agent' => $agent->id]))
        ->assertForbidden();
});

it('lets members view the agent list and detail', function () {
    [$user, $team] = memberOfTeam(TeamRole::Member);
    $agent = Agent::factory()->for($team)->create();

    $this->actingAs($user)
        ->get(route('agents.index', ['current_team' => $team->slug]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('permissions.canManageAgents', false),
        );

    $this->actingAs($user)
        ->get(route('agents.show', ['current_team' => $team->slug, 'agent' => $agent->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('agents/show'));
});

it('blocks access to another team\'s prefix', function () {
    [$user] = memberOfTeam(TeamRole::Owner);
    $otherTeam = Team::factory()->create();

    $this->actingAs($user)
        ->get(route('agents.index', ['current_team' => $otherTeam->slug]))
        ->assertForbidden();
});

it('cannot resolve a agent from another team via its own prefix', function () {
    [$user, $team] = memberOfTeam(TeamRole::Owner);
    $otherAgent = Agent::factory()->create();

    $this->actingAs($user)
        ->get(route('agents.show', ['current_team' => $team->slug, 'agent' => $otherAgent->id]))
        ->assertNotFound();
});

it('validates the agent name and origins', function () {
    [$user, $team] = memberOfTeam(TeamRole::Owner);

    $this->actingAs($user)
        ->post(route('agents.store', ['current_team' => $team->slug]), [
            'name' => '',
            'embed_origins' => ['https://example.com/with/path'],
        ])
        ->assertInvalid(['name', 'embed_origins.0']);
});
