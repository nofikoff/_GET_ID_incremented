<?php

use App\Models\User;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request as GuzzleRequest;
use Laravel\Socialite\Facades\Socialite;

beforeEach(function () {
    config(['getid.allowed_email_domain' => 'cas.ai', 'getid.admin_emails' => []]);

    // Google unreachable at the moment the callback exchanges the code.
    Socialite::fake('google', fn () => throw new ConnectException(
        'cURL error 7: Failed to connect to oauth2.googleapis.com port 443',
        new GuzzleRequest('POST', 'https://oauth2.googleapis.com/token'),
    ));
});

// FR-021c: an outage blocks only people signing in.
test('with Google unreachable, signing in is refused without an account being created', function () {
    $this->get(route('auth.google.callback'))->assertRedirect(route('login'));

    $this->assertGuest();
    expect(User::query()->count())->toBe(0);
});

test('issued tokens keep working while Google is unreachable', function () {
    enabledPair();
    $token = User::factory()->create()->createToken('laptop')->plainTextToken;

    $this->withToken($token)
        ->postJson('api/v1/sequence/next', ['project_key' => 'gitlab.cas.ai/team/backend', 'type' => 'ADR', 'name' => 'add-oauth-auth'])
        ->assertOk()
        ->assertJsonPath('sequence_number', 1);
});
