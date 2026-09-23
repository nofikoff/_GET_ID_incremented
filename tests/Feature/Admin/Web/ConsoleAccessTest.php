<?php

use App\Actions\ChangeUserRole;
use App\Enums\UserRole;
use App\Models\Identifier;
use App\Models\KeyType;
use App\Models\Project;
use App\Models\ProjectKeyType;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// FR-016, FR-017: every console page and form is the administrator's alone, and every form needs its CSRF token.

beforeEach(function () {
    $this->project = Project::factory()->create(['repo_url' => 'git@gitlab.cas.ai:team/secret.git', 'name' => 'Secret']);
    $this->keyType = KeyType::factory()->create(['code' => 'ADR']);

    // Every route of contracts/web-console.md, with input an administrator would get accepted.
    $this->operations = fn (int $projectId, int $keyTypeId): array => [
        ['GET', 'admin.projects.index', [], []],
        ['GET', 'admin.projects.create', [], []],
        ['POST', 'admin.projects.store', [], ['repo_url' => 'git@gitlab.cas.ai:team/new.git', 'name' => 'New']],
        ['GET', 'admin.projects.show', ['project' => $projectId], []],
        ['PATCH', 'admin.projects.update', ['project' => $projectId], ['name' => 'Renamed']],
        ['PUT', 'admin.projects.key-types.update', ['project' => $projectId], ['types' => ['ADR' => ['enabled' => '1', 'seed_sequence' => '']]]],
        ['GET', 'admin.key-types.index', [], []],
    ];
    $this->writes = fn (): array => array_values(array_filter(
        ($this->operations)($this->project->id, $this->keyType->id),
        fn (array $operation): bool => $operation[0] !== 'GET',
    ));

    // Everything a refused request could have written.
    $this->registry = fn (): array => [
        Project::query()->get()->toArray(),
        KeyType::query()->get()->toArray(),
        ProjectKeyType::query()->get()->toArray(),
        Identifier::query()->get()->toArray(),
    ];
});

// The list above is what the other tests walk, so a console route missing from it would go unchecked.
test('every console route is in the list the access tests walk', function () {
    $listed = collect(($this->operations)(1, 1))->pluck(1)->sort()->values();
    $registered = collect(Route::getRoutes()->getRoutesByName())->keys()
        ->filter(fn (string $name): bool => str_starts_with($name, 'admin.'))
        ->sort()
        ->values();

    expect($registered->all())->toBe($listed->all());
});

test('a member is refused every page and form, sees nothing of the registry and changes nothing', function () {
    $this->actingAs(User::factory()->create());
    $before = ($this->registry)();

    foreach (($this->operations)($this->project->id, $this->keyType->id) as [$method, $name, $parameters, $input]) {
        $response = $this->call($method, route($name, $parameters), $input)->assertForbidden();

        expect($response->getContent())->not->toContain('gitlab.cas.ai/team/secret')->not->toContain('Secret');
    }

    expect(($this->registry)())->toEqual($before);
});

// FR-016: the role is checked before the lookup, so the answer never tells a member whether an id exists.
test('a member gets the same refusal for an id that does not exist', function () {
    $this->actingAs(User::factory()->create());

    $answers = fn (array $operations) => collect($operations)->map(function (array $operation): array {
        [$method, $name, $parameters, $input] = $operation;
        $response = $this->call($method, route($name, $parameters), $input);

        return [$response->status(), $response->getContent()];
    });

    $existing = $answers(($this->operations)($this->project->id, $this->keyType->id));
    $missing = $answers(($this->operations)(999999, 999999));

    expect($missing->all())->toBe($existing->all())
        ->and($missing->pluck(0)->unique()->all())->toBe([403]);
});

test('a guest is sent to sign in from every page and form', function () {
    foreach (($this->operations)($this->project->id, $this->keyType->id) as [$method, $name, $parameters, $input]) {
        $this->call($method, route($name, $parameters), $input)->assertRedirect(route('login'));
    }

    expect(Project::query()->count())->toBe(1);
});

test('an administrator is let through every page and form', function () {
    $this->actingAs(User::factory()->admin()->create());

    foreach (($this->operations)($this->project->id, $this->keyType->id) as [$method, $name, $parameters, $input]) {
        $response = $this->call($method, route($name, $parameters), $input);

        expect($response->status())->toBe($method === 'GET' ? 200 : 302, "{$method} {$name}");
        $response->assertSessionHasNoErrors();
    }
});

// FR-016: the role is read on every request, so taking it away needs no new sign-in to take effect.
test('an administrator whose role is taken away is refused from the next request of the same session', function () {
    $admin = User::factory()->admin()->create();
    User::factory()->admin()->create();
    $session = [Auth::guard('web')->getName() => $admin->getKey()];

    $this->withSession($session)->get(route('admin.projects.show', $this->project))->assertOk();

    app(ChangeUserRole::class)($admin, UserRole::Member);
    // In production each request is its own process; the guard must not carry the user it resolved into the next one.
    Auth::forgetGuards();

    $this->withSession($session)->get(route('admin.projects.show', $this->project))->assertForbidden();
});

// FR-017, Edge Cases: an expired page cannot change the registry.
test('no console form changes anything without the session\'s CSRF token', function () {
    $this->actingAs(User::factory()->admin()->create());
    // The framework skips the check under tests; this puts it back.
    $this->app->bind(PreventRequestForgery::class, fn ($app) => new class($app, $app['encrypter']) extends PreventRequestForgery
    {
        protected function runningUnitTests(): bool
        {
            return false;
        }
    });
    $before = ($this->registry)();

    foreach (($this->writes)() as [$method, $name, $parameters, $input]) {
        $this->call($method, route($name, $parameters), $input)->assertStatus(419);
    }
    expect(($this->registry)())->toEqual($before);

    // The same forms carrying the session's token go through: the refusal above was the token's.
    $this->withSession(['_token' => 'console-csrf-token']);
    foreach (($this->writes)() as [$method, $name, $parameters, $input]) {
        $this->call($method, route($name, $parameters), [...$input, '_token' => 'console-csrf-token'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();
    }
});
