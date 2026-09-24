<?php

use App\Models\User;

// Google brand verification: a public homepage that describes the app and links the privacy policy it is registered with.

test('a guest at the root sees what the service does, its policies and the Google sign-in', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('issues sequential numbers')
        ->assertSee(route('privacy'))
        ->assertSee(route('terms'))
        ->assertSee(route('auth.google.redirect'))
        ->assertSee('Sign in with Google');
});

test('the policies open without signing in and for a signed-in employee', function (string $route) {
    $this->get(route($route))->assertOk()->assertSee('ruslan.novikov@cas.ai');

    $this->actingAs(User::factory()->create());
    $this->get(route($route))->assertOk();
})->with(['privacy', 'terms']);

test('every page links both policies', function () {
    $this->get(route('login'))->assertOk()->assertSee(route('privacy'))->assertSee(route('terms'));

    $this->actingAs(User::factory()->create());
    $this->get(route('tokens.index'))->assertOk()->assertSee(route('privacy'))->assertSee(route('terms'));
});

test('the privacy policy states the retention the service is configured with', function () {
    config([
        'getid.api_log_retention_days' => 42,
        'logging.channels.stack.channels' => ['daily'],
        'logging.channels.daily.max_files' => 365,
        'session.lifetime' => 77,
    ]);

    $this->get(route('privacy'))
        ->assertOk()
        ->assertSee('Kept for 42 days.')
        ->assertSee('Kept for 365 days.')
        ->assertSee('after 77 minutes of inactivity');
});

test('without daily rotation the policy does not promise a log retention period', function () {
    config(['logging.channels.stack.channels' => ['single']]);

    $this->get(route('privacy'))->assertOk()->assertSee('Kept until the log is cleared by the operator.');
});
