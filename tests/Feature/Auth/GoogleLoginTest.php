<?php

use App\Enums\UserRole;
use App\Models\User;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as GoogleUser;

beforeEach(function () {
    config([
        'services.google' => ['client_id' => 'test-client', 'client_secret' => 'test-secret', 'redirect' => 'http://localhost/auth/google/callback'],
        'getid.allowed_email_domain' => 'cas.ai',
        'getid.admin_emails' => [],
    ]);

    // Google's answer at the callback; everything before it is Google's side of the flow.
    $this->returnFromGoogle = function (string $email, string $googleId = '1001') {
        Socialite::fake('google', GoogleUser::fake([
            'id' => $googleId,
            'name' => 'Ada Lovelace',
            'email' => $email,
            'avatar' => 'https://lh3.googleusercontent.com/a/ada',
        ]));

        return $this->get(route('auth.google.callback'));
    };
});

test('the sign-in page offers Google', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertSee(route('auth.google.redirect'));
});

test('signing in sends the browser to Google', function () {
    $location = $this->get(route('auth.google.redirect'))->assertRedirect()->headers->get('Location');

    expect($location)->toStartWith('https://accounts.google.com/')->toContain('client_id=test-client');
});

test('a corporate account signs in and gets a member account', function (string $email) {
    ($this->returnFromGoogle)($email)->assertRedirect(route('tokens.index'));

    $user = User::query()->sole();
    expect($user->google_id)->toBe('1001')
        ->and(mb_strtolower($user->email))->toBe('ada@cas.ai')
        ->and($user->name)->toBe('Ada Lovelace')
        ->and($user->avatar_url)->toBe('https://lh3.googleusercontent.com/a/ada')
        ->and($user->role)->toBe(UserRole::Member);
    $this->assertAuthenticatedAs($user);
})->with([
    'as Google spells it' => 'ada@cas.ai',
    'domain in another case' => 'ada@CAS.ai',
]);

// FR-018: the domain is checked before any account exists, so a refused sign-in leaves nothing behind.
test('an account outside the corporate domain is refused and no account is created', function (string $email) {
    ($this->returnFromGoogle)($email)->assertRedirect(route('login'));

    $this->assertGuest();
    expect(User::query()->count())->toBe(0);
})->with([
    'other domain' => 'eve@gmail.com',
    'corporate domain as a prefix' => 'eve@cas.ai.evil.com',
    'corporate domain as a suffix' => 'eve@evilcas.ai',
    'subdomain' => 'eve@mail.cas.ai',
]);

test('the corporate domain comes from configuration', function () {
    config(['getid.allowed_email_domain' => 'example.org']);

    ($this->returnFromGoogle)('ada@cas.ai')->assertRedirect(route('login'));
    ($this->returnFromGoogle)('ada@example.org')->assertRedirect(route('tokens.index'));

    expect(User::query()->pluck('email')->all())->toBe(['ada@example.org']);
});

test('an existing account signs in without a duplicate', function () {
    $ada = User::factory()->create(['email' => 'ada@cas.ai', 'google_id' => '1001']);

    ($this->returnFromGoogle)('ada@cas.ai')->assertRedirect(route('tokens.index'));

    $this->assertAuthenticatedAs($ada);
    expect(User::query()->count())->toBe(1);
});

// FR-021: the first administrator comes from deployment configuration, applied when that person first signs in.
test('an address listed in ADMIN_EMAILS becomes an administrator on first sign-in', function () {
    config(['getid.admin_emails' => ['boss@cas.ai']]);

    ($this->returnFromGoogle)('boss@cas.ai', '2002')->assertRedirect(route('tokens.index'));

    expect(User::query()->sole()->role)->toBe(UserRole::Admin);
});

test('an address not listed in ADMIN_EMAILS stays a member', function () {
    config(['getid.admin_emails' => ['boss@cas.ai']]);

    ($this->returnFromGoogle)('ada@cas.ai')->assertRedirect(route('tokens.index'));

    expect(User::query()->sole()->role)->toBe(UserRole::Member);
});

// Later role changes belong to user:role; a listed address must not undo a demotion on every sign-in.
test('ADMIN_EMAILS does not override the role of an existing account', function () {
    config(['getid.admin_emails' => ['boss@cas.ai']]);
    $boss = User::factory()->create(['email' => 'boss@cas.ai', 'google_id' => '2002']);

    ($this->returnFromGoogle)('boss@cas.ai', '2002')->assertRedirect(route('tokens.index'));

    expect($boss->refresh()->role)->toBe(UserRole::Member);
});
