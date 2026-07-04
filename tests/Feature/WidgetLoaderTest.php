<?php

use App\Enums\TeamRole;
use App\Models\Agent;
use App\Models\Team;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('serves the widget loader as javascript with the embed contract', function () {
    $response = $this->get('/widget.js');

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('javascript');

    $body = $response->getContent();

    // The embed contract: reads data-agent-id, posts to the widget chat endpoint,
    // persists a session id, and isolates styles via Shadow DOM.
    expect($body)->toContain('data-agent-id')
        ->and($body)->toContain('/api/widget/')
        ->and($body)->toContain('/chat')
        ->and($body)->toContain('session_id')
        ->and($body)->toContain('localStorage')
        ->and($body)->toContain('attachShadow')
        ->and($body)->toContain('429');
});

it('shows the copy-paste embed snippet on the agent detail page', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);
    $agent = Agent::factory()->for($team)->create();

    $this->actingAs($user)
        ->get(route('agents.show', ['current_team' => $team->slug, 'agent' => $agent->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('agents/show')
            ->where('widgetScriptUrl', url('/widget.js')),
        );
});
