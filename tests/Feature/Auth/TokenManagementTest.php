<?php

use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    // A Sanctum plain-text token as printed on a page: "<id>|<secret>".
    $this->plainTokenIn = fn (string $html): ?string => preg_match('/\b(\d+\|[A-Za-z0-9]{40,})\b/', $html, $match) === 1 ? $match[1] : null;

    $this->api = function (string $token) {
        // Drop the session user of actingAs and the cached sanctum user, so only the bearer token authenticates.
        $this->app['auth']->forgetGuards();

        return $this->withToken($token)->getJson('api/v1/projects/resolve?origin=gitlab.cas.ai/team/backend');
    };
});

test('the token cabinet requires signing in', function () {
    $this->app['auth']->forgetGuards();

    $this->get(route('tokens.index'))->assertRedirect(route('login'));
});

// FR-019a: the value is shown once, when created, and never again.
test('a new token value is shown once and then never again', function () {
    $page = $this->followingRedirects()->post(route('tokens.store'), ['name' => 'laptop'])->assertOk()->getContent();
    $plain = ($this->plainTokenIn)($page);

    expect($plain)->not->toBeNull();
    expect(PersonalAccessToken::findToken($plain))
        ->not->toBeNull()
        ->name->toBe('laptop')
        ->tokenable_id->toBe($this->user->id);

    expect($this->get(route('tokens.index'))->assertOk()->assertSee('laptop')->getContent())->not->toContain($plain);
});

test('a member holds several named tokens', function () {
    $this->post(route('tokens.store'), ['name' => 'laptop'])->assertRedirect(route('tokens.index'));
    $this->post(route('tokens.store'), ['name' => 'ci'])->assertRedirect(route('tokens.index'));

    expect($this->user->tokens()->pluck('name')->sort()->values()->all())->toBe(['ci', 'laptop']);
    $this->get(route('tokens.index'))->assertSee('laptop')->assertSee('ci');
});

test('the list shows each token with its dates, and never a secret or another member\'s token', function () {
    $this->travelTo('2026-09-20 14:30:00');
    $token = $this->user->createToken('laptop');
    $token->accessToken->forceFill(['last_used_at' => '2026-09-21 09:00:00'])->save();
    User::factory()->create()->createToken('someone-elses');

    $html = $this->get(route('tokens.index'))
        ->assertOk()
        ->assertSee('laptop')
        ->assertDontSee('someone-elses')
        ->assertDontSee($token->accessToken->token)
        ->assertDontSee(explode('|', $token->plainTextToken)[1])
        ->getContent();

    expect($html)->toMatch('/2026-09-20|20\.09\.2026/')->toMatch('/2026-09-21|21\.09\.2026/');
});

test('a token needs a name of its own', function (array $input) {
    $this->user->createToken('laptop');

    $this->post(route('tokens.store'), $input)->assertSessionHasErrors('name');

    expect($this->user->tokens()->count())->toBe(1);
})->with([
    'no name' => [[]],
    'name already taken' => [['name' => 'laptop']],
    'too long' => [['name' => str_repeat('n', 256)]],
]);

// FR-019: revocation takes effect on the next request and touches no other token.
test('revoking one token stops it at once and leaves the others working', function () {
    $laptop = $this->user->createToken('laptop');
    $ci = $this->user->createToken('ci');

    ($this->api)($laptop->plainTextToken)->assertOk();

    $this->delete(route('tokens.destroy', $laptop->accessToken->id))->assertRedirect(route('tokens.index'));

    ($this->api)($laptop->plainTextToken)->assertUnauthorized();
    ($this->api)($ci->plainTextToken)->assertOk();
});

test('a member cannot revoke another member\'s token', function () {
    $foreign = User::factory()->create()->createToken('theirs');

    $this->delete(route('tokens.destroy', $foreign->accessToken->id))->assertNotFound();

    expect(PersonalAccessToken::query()->find($foreign->accessToken->id))->not->toBeNull();
});
