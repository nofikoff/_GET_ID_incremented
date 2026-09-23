<?php

use App\Enums\UserRole;
use App\Models\Identifier;
use App\Models\User;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as GoogleUser;

// FR-021: role and deactivation are console-only. Arguments are positional, as in `user:role {email} {admin|member}`.

test('an administrator is appointed and demoted from the command line', function () {
    User::factory()->admin()->create(['email' => 'boss@cas.ai']);
    $ada = User::factory()->create(['email' => 'ada@cas.ai']);

    $this->artisan('user:role ada@cas.ai admin')->assertSuccessful();
    expect($ada->refresh()->role)->toBe(UserRole::Admin);

    $this->artisan('user:role ada@cas.ai member')->assertSuccessful();
    expect($ada->refresh()->role)->toBe(UserRole::Member);
});

test('the commands find an account whatever the case of the address typed', function () {
    User::factory()->admin()->create();
    $ada = User::factory()->create(['email' => 'ada@cas.ai']);

    $this->artisan('user:role Ada@CAS.ai admin')->assertSuccessful();
    $this->artisan('user:deactivate ADA@cas.ai')->assertSuccessful();

    expect($ada->refresh())->role->toBe(UserRole::Admin)->deactivated_at->not->toBeNull();
});

// FR-021a: without an administrator the registry becomes unmanageable short of editing the database.
test('the last active administrator cannot be demoted', function () {
    $boss = User::factory()->admin()->create(['email' => 'boss@cas.ai']);
    User::factory()->admin()->create(['email' => 'gone@cas.ai', 'deactivated_at' => now()]);

    $this->artisan('user:role boss@cas.ai member')->assertNotExitCode(0);

    expect($boss->refresh()->role)->toBe(UserRole::Admin);
});

test('the role command refuses an unknown address or role', function (string $command) {
    $ada = User::factory()->create(['email' => 'ada@cas.ai']);

    $this->artisan($command)->assertNotExitCode(0);

    expect($ada->refresh()->role)->toBe(UserRole::Member);
})->with([
    'unknown address' => 'user:role nobody@cas.ai admin',
    'unknown role' => 'user:role ada@cas.ai owner',
]);

// FR-021b: the person leaves, their tokens go with them, their issued numbers stay theirs.
test('deactivating an employee removes every token and keeps their numbers attributed', function () {
    User::factory()->admin()->create();
    $ada = User::factory()->create(['email' => 'ada@cas.ai']);
    $laptop = $ada->createToken('laptop');
    $ada->createToken('ci');
    $identifier = Identifier::factory()->create(['created_by' => $ada->id]);

    $this->artisan('user:deactivate ada@cas.ai')->assertSuccessful();

    expect($ada->refresh()->deactivated_at)->not->toBeNull()
        ->and($ada->tokens()->count())->toBe(0)
        ->and($identifier->refresh()->created_by)->toBe($ada->id);
    $this->withToken($laptop->plainTextToken)
        ->getJson('api/v1/projects/resolve?origin=gitlab.cas.ai/team/backend')
        ->assertUnauthorized();
});

test('the last active administrator cannot be deactivated', function () {
    $boss = User::factory()->admin()->create(['email' => 'boss@cas.ai']);
    $boss->createToken('laptop');

    $this->artisan('user:deactivate boss@cas.ai')->assertNotExitCode(0);

    expect($boss->refresh()->deactivated_at)->toBeNull()
        ->and($boss->tokens()->count())->toBe(1);
});

test('deactivating an unknown address fails', function () {
    $this->artisan('user:deactivate nobody@cas.ai')->assertNotExitCode(0);
});

test('a deactivated employee cannot sign in again', function () {
    config(['getid.allowed_email_domain' => 'cas.ai', 'getid.admin_emails' => []]);
    User::factory()->create(['email' => 'ada@cas.ai', 'google_id' => '1001', 'deactivated_at' => now()]);
    Socialite::fake('google', GoogleUser::fake(['id' => '1001', 'name' => 'Ada Lovelace', 'email' => 'ada@cas.ai']));

    $this->get(route('auth.google.callback'))->assertRedirect(route('login'));

    $this->assertGuest();
});
