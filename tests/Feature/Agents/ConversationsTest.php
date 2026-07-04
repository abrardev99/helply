<?php

use App\Enums\MessageRole;
use App\Enums\TeamRole;
use App\Models\Agent;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Team;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * @return array{0: User, 1: Team}
 */
function conversationsMember(TeamRole $role): array
{
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => $role->value]);
    $user->switchTeam($team);

    return [$user, $team];
}

it('lists a agent conversations scoped to the team', function () {
    [$user, $team] = conversationsMember(TeamRole::Owner);
    $agent = Agent::factory()->for($team)->create();

    $conversation = Conversation::factory()->for($agent)->create();
    Message::factory()->for($conversation)->create(['role' => MessageRole::User, 'flagged' => false]);
    Message::factory()->for($conversation)->create(['role' => MessageRole::Assistant, 'flagged' => false]);

    $this->actingAs($user)
        ->get(route('agents.conversations.index', ['current_team' => $team->slug, 'agent' => $agent->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('agents/conversations/index')
            ->has('conversations', 1)
            ->where('analytics.conversations', 1)
            ->where('analytics.messages', 2)
            ->where('analytics.flagged', 0),
        );
});

it('filters to conversations with flagged messages', function () {
    [$user, $team] = conversationsMember(TeamRole::Owner);
    $agent = Agent::factory()->for($team)->create();

    $plain = Conversation::factory()->for($agent)->create();
    Message::factory()->for($plain)->create(['role' => MessageRole::Assistant, 'flagged' => false]);

    $flaggedConversation = Conversation::factory()->for($agent)->create();
    Message::factory()->for($flaggedConversation)->create(['role' => MessageRole::Assistant, 'flagged' => true]);

    $this->actingAs($user)
        ->get(route('agents.conversations.index', ['current_team' => $team->slug, 'agent' => $agent->id, 'flagged' => 1]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.flagged', true)
            ->has('conversations', 1)
            ->where('conversations.0.id', $flaggedConversation->id)
            ->where('conversations.0.flagged', true),
        );
});

it('shows a conversation transcript in order', function () {
    [$user, $team] = conversationsMember(TeamRole::Owner);
    $agent = Agent::factory()->for($team)->create();
    $conversation = Conversation::factory()->for($agent)->create();

    $first = Message::factory()->for($conversation)->create(['role' => MessageRole::User, 'content' => 'First', 'created_at' => now()->subMinute()]);
    $second = Message::factory()->for($conversation)->create(['role' => MessageRole::Assistant, 'content' => 'Second', 'created_at' => now()]);

    $this->actingAs($user)
        ->get(route('agents.conversations.show', ['current_team' => $team->slug, 'agent' => $agent->id, 'conversation' => $conversation->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('agents/conversations/show')
            ->has('messages', 2)
            ->where('messages.0.id', $first->id)
            ->where('messages.1.id', $second->id),
        );
});

it('does not expose another team conversation', function () {
    [$user, $team] = conversationsMember(TeamRole::Owner);
    $agent = Agent::factory()->for($team)->create();

    $otherAgent = Agent::factory()->create();
    $otherConversation = Conversation::factory()->for($otherAgent)->create();

    // Cross-team agent in own prefix -> 404 (route binding scoping).
    $this->actingAs($user)
        ->get(route('agents.conversations.index', ['current_team' => $team->slug, 'agent' => $otherAgent->id]))
        ->assertNotFound();

    // Foreign conversation id under an owned agent -> 404 (resolved through the agent relation).
    $this->actingAs($user)
        ->get(route('agents.conversations.show', ['current_team' => $team->slug, 'agent' => $agent->id, 'conversation' => $otherConversation->id]))
        ->assertNotFound();
});

it('blocks a non-member from viewing conversations', function () {
    [, $team] = conversationsMember(TeamRole::Owner);
    $agent = Agent::factory()->for($team)->create();

    $outsider = User::factory()->create();

    $this->actingAs($outsider)
        ->get(route('agents.conversations.index', ['current_team' => $team->slug, 'agent' => $agent->id]))
        ->assertForbidden();
});
