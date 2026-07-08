<?php

use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Pennant\Feature;

/**
 * Force the "auth" feature off, mimicking a non-local environment.
 */
function disableAuthFeature(): void
{
    Feature::define('auth', fn (): bool => false);
    Feature::flushCache();
}

test('login and register are reachable while the auth feature is active', function () {
    // The feature resolves to active in the testing environment by default.
    $this->get(route('login'))->assertOk();
    $this->get(route('register'))->assertOk();
});

test('login is not accessible when the auth feature is off', function () {
    disableAuthFeature();

    $this->get(route('login'))->assertNotFound();
    $this->post(route('login'), [
        'email' => 'someone@example.com',
        'password' => 'password',
    ])->assertNotFound();
});

test('register is not accessible when the auth feature is off', function () {
    disableAuthFeature();

    $this->get(route('register'))->assertNotFound();
    $this->post(route('register.store'), [
        'name' => 'Someone',
        'email' => 'someone@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertNotFound();
});

test('the welcome page stays public and hides the log in link when auth is off', function () {
    disableAuthFeature();

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('welcome')
            ->where('features.auth', false),
        );
});

test('the waitlist stays open when auth is off', function () {
    disableAuthFeature();

    $this->from(route('home'))
        ->post(route('waitlist.store'), ['email' => 'founder@example.com'])
        ->assertRedirect(route('home'))
        ->assertSessionHasNoErrors();
});
