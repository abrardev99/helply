<?php

use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Record every query fired while the callback runs.
 *
 * @return list<string>
 */
function queriesDuring(Closure $callback): array
{
    $queries = [];

    Event::listen(QueryExecuted::class, function (QueryExecuted $query) use (&$queries): void {
        $queries[] = $query->sql;
    });

    $callback();

    return $queries;
}

/**
 * The suite runs with array session and Pennant stores, which would hide a regression
 * here. Point both at the database so the assertion exercises what production does.
 */
function usingDatabaseBackedState(): void
{
    config([
        'session.driver' => 'database',
        'pennant.default' => 'database',
    ]);
}

test('the welcome page renders', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('welcome'));
});

test('the welcome page runs no database queries', function () {
    usingDatabaseBackedState();

    $queries = queriesDuring(fn () => $this->get(route('home'))->assertOk());

    expect($queries)->toBe([]);
});

test('the welcome page runs no database queries for a signed-in user', function () {
    usingDatabaseBackedState();

    $user = User::factory()->create();

    $queries = queriesDuring(fn () => $this->actingAs($user)->get(route('home'))->assertOk());

    expect($queries)->toBe([]);
});

test('the welcome page shares no server state with the client', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('welcome')
            ->missing('auth')
            ->missing('features')
            ->missing('currentTeam')
            ->missing('teams'),
        );
});

test('the waitlist endpoint is gone', function () {
    $this->post('/waitlist', ['email' => 'founder@example.com'])->assertNotFound();
});
