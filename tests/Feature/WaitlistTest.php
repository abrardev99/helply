<?php

use App\Models\WaitlistEntry;

test('the landing page renders', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('welcome'));
});

test('a visitor can join the waitlist', function () {
    $response = $this->from(route('home'))->post(route('waitlist.store'), [
        'email' => 'founder@example.com',
    ]);

    $response->assertRedirect(route('home'));
    $response->assertSessionHasNoErrors();

    expect(WaitlistEntry::where('email', 'founder@example.com')->exists())->toBeTrue();
});

test('the email is normalized before it is stored', function () {
    $this->post(route('waitlist.store'), [
        'email' => '  Founder@Example.COM ',
    ])->assertSessionHasNoErrors();

    expect(WaitlistEntry::sole()->email)->toBe('founder@example.com');
});

test('the email is required', function () {
    $this->from(route('home'))
        ->post(route('waitlist.store'), ['email' => ''])
        ->assertSessionHasErrors('email');

    expect(WaitlistEntry::count())->toBe(0);
});

test('an invalid email is rejected', function () {
    $this->from(route('home'))
        ->post(route('waitlist.store'), ['email' => 'not-an-email'])
        ->assertSessionHasErrors('email');

    expect(WaitlistEntry::count())->toBe(0);
});

test('the same email cannot join twice', function () {
    WaitlistEntry::factory()->create(['email' => 'dupe@example.com']);

    $this->from(route('home'))
        ->post(route('waitlist.store'), ['email' => 'dupe@example.com'])
        ->assertSessionHasErrors('email');

    expect(WaitlistEntry::where('email', 'dupe@example.com')->count())->toBe(1);
});
