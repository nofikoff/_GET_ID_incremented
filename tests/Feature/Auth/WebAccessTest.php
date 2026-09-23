<?php

use App\Models\KeyType;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

test('the root leads a guest to the sign-in page', function () {
    $this->followingRedirects()->get('/')->assertOk()->assertSee(route('auth.google.redirect'));
});

test('the root takes a signed-in member to the token cabinet', function () {
    $this->actingAs(User::factory()->create());

    $this->get('/')->assertRedirect(route('tokens.index'));
});

test('a signed-in member visiting the sign-in page lands in the token cabinet', function () {
    $this->actingAs(User::factory()->create());

    $this->followingRedirects()->get(route('login'))->assertOk()->assertSee(route('tokens.store'));
});

test('signing out ends the session', function () {
    $this->actingAs(User::factory()->create());

    $this->post(route('logout'))->assertRedirect(route('login'));

    $this->assertGuest();
});

test('an administrator sees the registry screens', function () {
    $this->actingAs(User::factory()->admin()->create());
    $pair = enabledPair(counter: ['seed_sequence' => 42]);
    KeyType::factory()->create(['code' => 'spec']);

    $this->get(route('admin.projects.index'))
        ->assertOk()
        ->assertSee('gitlab.cas.ai/team/backend')
        ->assertSee($pair->project->name)
        ->assertSee('ADR');

    $this->get(route('admin.key-types.index'))
        ->assertOk()
        ->assertSee('ADR')
        ->assertSee('spec');

    $this->get(route('tokens.index'))
        ->assertSee(route('admin.projects.index'))
        ->assertSee(route('admin.key-types.index'));
});

// FR-017: the web screens refuse members exactly as the admin API does.
test('a member is refused the registry screens and is not offered them', function () {
    $this->actingAs(User::factory()->create());
    enabledPair();

    $this->get(route('admin.projects.index'))->assertForbidden()->assertDontSee('gitlab.cas.ai/team/backend');
    $this->get(route('admin.key-types.index'))->assertForbidden();

    $this->get(route('tokens.index'))
        ->assertOk()
        ->assertDontSee(route('admin.projects.index'))
        ->assertDontSee(route('admin.key-types.index'));
});

test('a guest is sent to sign in from every closed screen', function (string $route) {
    $this->get(route($route))->assertRedirect(route('login'));
})->with(['tokens.index', 'admin.projects.index', 'admin.key-types.index']);

// FR-021b: a browser session opened before deactivation must not outlive it, or it could mint new tokens.
test('a deactivated employee\'s open session no longer authenticates', function () {
    $ada = User::factory()->create(['deactivated_at' => now()]);

    $this->withSession([Auth::guard('web')->getName() => $ada->getKey()])
        ->get(route('tokens.index'))
        ->assertRedirect(route('login'));

    $this->assertGuest();
});

test('an active employee\'s session keeps authenticating', function () {
    $ada = User::factory()->create();

    $this->withSession([Auth::guard('web')->getName() => $ada->getKey()])
        ->get(route('tokens.index'))
        ->assertOk();
});
