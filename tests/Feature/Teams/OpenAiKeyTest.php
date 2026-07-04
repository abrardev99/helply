<?php

use App\Ai\Exceptions\MissingOpenAiKeyException;
use App\Ai\Support\ResolvesTenantKey;
use App\Enums\TeamRole;
use App\Models\Agent;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * @return array{0: User, 1: Team}
 */
function keyMember(TeamRole $role): array
{
    $user = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($user, ['role' => $role->value]);
    $user->switchTeam($team);

    return [$user, $team];
}

it('stores the OpenAI key encrypted at rest', function () {
    [$user, $team] = keyMember(TeamRole::Owner);

    $this->actingAs($user)
        ->put(route('teams.openai-key.update', ['team' => $team->slug]), [
            'openai_api_key' => 'sk-test-secret-value',
        ])
        ->assertRedirect();

    // The model returns the decrypted value...
    expect($team->fresh()->openai_api_key)->toBe('sk-test-secret-value');

    // ...but the raw column is ciphertext, never the plaintext key.
    $raw = DB::table('teams')->where('id', $team->id)->value('openai_api_key');
    expect($raw)->not->toBe('sk-test-secret-value')
        ->and($raw)->not->toContain('sk-test-secret-value');
});

it('never sends key material to the browser, only a "set" flag', function () {
    [$user, $team] = keyMember(TeamRole::Owner);
    $team->openai_api_key = 'sk-super-secret-key';
    $team->save();

    $response = $this->actingAs($user)
        ->get(route('teams.edit', ['team' => $team->slug]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('team.hasOpenAiKey', true)
            ->missing('team.openai_api_key'),
        );

    expect($response->getContent())->not->toContain('sk-super-secret-key');
});

it('rejects a malformed key at save time', function () {
    [$user, $team] = keyMember(TeamRole::Owner);

    $this->actingAs($user)
        ->put(route('teams.openai-key.update', ['team' => $team->slug]), [
            'openai_api_key' => 'not-a-real-key',
        ])
        ->assertInvalid(['openai_api_key']);

    expect($team->fresh()->openai_api_key)->toBeNull();
});

it('lets an owner remove the key', function () {
    [$user, $team] = keyMember(TeamRole::Owner);
    $team->openai_api_key = 'sk-remove-me';
    $team->save();

    $this->actingAs($user)
        ->delete(route('teams.openai-key.destroy', ['team' => $team->slug]))
        ->assertRedirect();

    expect($team->fresh()->openai_api_key)->toBeNull();
});

it('forbids non-managers from setting the key', function () {
    [$user, $team] = keyMember(TeamRole::Member);

    $this->actingAs($user)
        ->put(route('teams.openai-key.update', ['team' => $team->slug]), [
            'openai_api_key' => 'sk-nope',
        ])
        ->assertForbidden();
});

it('resolves the agent override before the team key', function () {
    $team = Team::factory()->create(['openai_api_key' => 'sk-team-key']);
    $agent = Agent::factory()->for($team)->create(['openai_api_key' => 'sk-agent-key']);

    expect(app(ResolvesTenantKey::class)->resolveOpenAiKey($agent))->toBe('sk-agent-key');
});

it('falls back to the team key when the agent has no override', function () {
    $team = Team::factory()->create(['openai_api_key' => 'sk-team-key']);
    $agent = Agent::factory()->for($team)->create(['openai_api_key' => null]);

    expect(app(ResolvesTenantKey::class)->resolveOpenAiKey($agent))->toBe('sk-team-key');
});

it('throws a typed exception when no key is configured', function () {
    $team = Team::factory()->create(['openai_api_key' => null]);
    $agent = Agent::factory()->for($team)->create(['openai_api_key' => null]);

    app(ResolvesTenantKey::class)->resolveOpenAiKey($agent);
})->throws(MissingOpenAiKeyException::class);

it('applies the tenant key to the AI SDK config only within the callback scope', function () {
    config(['ai.providers.openai.key' => 'sk-platform-default']);

    $team = Team::factory()->create(['openai_api_key' => 'sk-tenant-key']);
    $agent = Agent::factory()->for($team)->create(['openai_api_key' => null]);

    $seenInside = app(ResolvesTenantKey::class)->withTenantKey($agent, function () {
        return config('ai.providers.openai.key');
    });

    expect($seenInside)->toBe('sk-tenant-key')
        ->and(config('ai.providers.openai.key'))->toBe('sk-platform-default');
});
