<?php

use App\Models\KeyType;
use App\Models\Project;
use App\Models\ProjectKeyType;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;

// SC-002, FR-018: every operation of the admin REST has a console path with the same result and the same refusal text.

beforeEach(function () {
    $this->travelTo('2026-09-23 10:00:00');
    $this->admin = User::factory()->admin()->create();
    $this->pair = enabledPair(counter: ['seed_sequence' => 7]);
    KeyType::factory()->create(['code' => 'spec']);

    $this->rest = function (string $method, string $uri, array $body): TestResponse {
        Sanctum::actingAs($this->admin);

        return $this->json($method, $uri, $body);
    };
    $this->console = function (string $method, string $route, array $parameters, array $input): TestResponse {
        $this->actingAs($this->admin, 'web');

        return $this->call($method, route($route, $parameters), $input);
    };

    // Ids aside, which a rolled-back insert still consumes.
    $this->registry = fn (): array => [
        Project::query()->orderBy('key')->get()->map(fn (Project $row): array => Arr::except($row->getAttributes(), 'id'))->all(),
        KeyType::query()->orderBy('code')->get()->map(fn (KeyType $row): array => Arr::except($row->getAttributes(), 'id'))->all(),
        ProjectKeyType::query()->orderBy('key_type_id')->get()->map(fn (ProjectKeyType $row): array => Arr::except($row->getAttributes(), 'id'))->all(),
    ];

    // The registry REST leaves behind, undone afterwards so the console starts from the very same one.
    $this->afterRest = function (array $call): array {
        DB::beginTransaction();

        try {
            ($this->rest)(...$call)->assertSuccessful();

            return ($this->registry)();
        } finally {
            DB::rollBack();
        }
    };
});

test('an accepted operation leaves the registry as REST leaves it', function (Closure $rest, Closure $console) {
    $before = ($this->registry)();
    $expected = ($this->afterRest)($rest($this->pair));

    ($this->console)(...$console($this->pair))->assertRedirect()->assertSessionHasNoErrors();

    expect(($this->registry)())->toBe($expected)->not->toBe($before);
})->with([
    'register a project' => [
        fn () => ['POST', 'api/v1/admin/projects', ['repo_url' => 'git@gitlab.cas.ai:team/frontend.git', 'name' => 'Frontend', 'description' => 'SPA']],
        fn () => ['POST', 'admin.projects.store', [], ['repo_url' => 'git@gitlab.cas.ai:team/frontend.git', 'name' => 'Frontend', 'description' => 'SPA']],
    ],
    'rename a project' => [
        fn (ProjectKeyType $pair) => ['PATCH', "api/v1/admin/projects/{$pair->project_id}", ['name' => 'Backend API', 'description' => 'Renamed']],
        fn (ProjectKeyType $pair) => ['PATCH', 'admin.projects.update', ['project' => $pair->project_id], ['name' => 'Backend API', 'description' => 'Renamed']],
    ],
    'retire a project' => [
        fn (ProjectKeyType $pair) => ['PATCH', "api/v1/admin/projects/{$pair->project_id}", ['is_active' => false]],
        fn (ProjectKeyType $pair) => ['PATCH', 'admin.projects.update', ['project' => $pair->project_id], ['is_active' => '0']],
    ],
    'set the key types of a project' => [
        fn (ProjectKeyType $pair) => ['PUT', "api/v1/admin/projects/{$pair->project_id}/key-types", ['types' => [['code' => 'ADR', 'seed_sequence' => 12], ['code' => 'spec']]]],
        fn (ProjectKeyType $pair) => ['PUT', 'admin.projects.key-types.update', ['project' => $pair->project_id], ['types' => [
            'ADR' => ['enabled' => '1', 'seed_sequence' => '12'],
            'spec' => ['enabled' => '1', 'seed_sequence' => ''],
        ]]],
    ],
    'drop a key type from a project' => [
        fn (ProjectKeyType $pair) => ['PUT', "api/v1/admin/projects/{$pair->project_id}/key-types", ['types' => [['code' => 'spec']]]],
        fn (ProjectKeyType $pair) => ['PUT', 'admin.projects.key-types.update', ['project' => $pair->project_id], ['types' => [
            'ADR' => ['seed_sequence' => ''],
            'spec' => ['enabled' => '1', 'seed_sequence' => ''],
        ]]],
    ],
    'register a key type' => [
        fn () => ['POST', 'api/v1/admin/key-types', ['code' => 'RFC', 'name' => 'Request for Comments', 'description' => 'Proposals']],
        fn () => ['POST', 'admin.key-types.store', [], ['code' => 'RFC', 'name' => 'Request for Comments', 'description' => 'Proposals']],
    ],
    'change a key type' => [
        fn (ProjectKeyType $pair) => ['PATCH', "api/v1/admin/key-types/{$pair->key_type_id}", ['name' => 'Decision', 'description' => 'Why']],
        fn (ProjectKeyType $pair) => ['PATCH', 'admin.key-types.update', ['keyType' => $pair->key_type_id], ['name' => 'Decision', 'description' => 'Why']],
    ],
    'retire a key type' => [
        fn (ProjectKeyType $pair) => ['PATCH', "api/v1/admin/key-types/{$pair->key_type_id}", ['is_active' => false]],
        fn (ProjectKeyType $pair) => ['PATCH', 'admin.key-types.update', ['keyType' => $pair->key_type_id], ['is_active' => '0']],
    ],
]);

test('a refused operation gets the text REST gives, under the form field, and changes nothing', function (Closure $arrange, Closure $rest, string $restField, Closure $console, string $consoleField) {
    $arrange($this->pair);
    $before = ($this->registry)();

    $restText = ($this->rest)(...$rest($this->pair))->assertStatus(422)->json('errors')[$restField][0];
    ($this->console)(...$console($this->pair))->assertRedirect()->assertSessionHasErrors($consoleField);

    expect(session('errors')->first($consoleField))->toBe($restText)
        ->and(($this->registry)())->toBe($before);
})->with([
    'repository registered under another address form' => [
        fn () => null,
        fn () => ['POST', 'api/v1/admin/projects', ['repo_url' => 'https://gitlab.cas.ai/team/backend', 'name' => 'Again']], 'repo_url',
        fn () => ['POST', 'admin.projects.store', [], ['repo_url' => 'https://gitlab.cas.ai/team/backend', 'name' => 'Again']], 'repo_url',
    ],
    'address that does not parse' => [
        fn () => null,
        fn () => ['POST', 'api/v1/admin/projects', ['repo_url' => 'not a repository', 'name' => 'Broken']], 'repo_url',
        fn () => ['POST', 'admin.projects.store', [], ['repo_url' => 'not a repository', 'name' => 'Broken']], 'repo_url',
    ],
    'project without a name' => [
        fn () => null,
        fn () => ['POST', 'api/v1/admin/projects', ['repo_url' => 'git@gitlab.cas.ai:team/frontend.git']], 'name',
        fn () => ['POST', 'admin.projects.store', [], ['repo_url' => 'git@gitlab.cas.ai:team/frontend.git']], 'name',
    ],
    'empty name on update' => [
        fn () => null,
        fn (ProjectKeyType $pair) => ['PATCH', "api/v1/admin/projects/{$pair->project_id}", ['name' => '']], 'name',
        fn (ProjectKeyType $pair) => ['PATCH', 'admin.projects.update', ['project' => $pair->project_id], ['name' => '']], 'name',
    ],
    'seed below the last issued number' => [
        fn (ProjectKeyType $pair) => $pair->update(['last_sequence' => 9]),
        fn (ProjectKeyType $pair) => ['PUT', "api/v1/admin/projects/{$pair->project_id}/key-types", ['types' => [['code' => 'spec'], ['code' => 'ADR', 'seed_sequence' => 3]]]], 'types.1.seed_sequence',
        fn (ProjectKeyType $pair) => ['PUT', 'admin.projects.key-types.update', ['project' => $pair->project_id], ['types' => [
            'spec' => ['enabled' => '1', 'seed_sequence' => ''],
            'ADR' => ['enabled' => '1', 'seed_sequence' => '3'],
        ]]], 'types.ADR.seed_sequence',
    ],
    'seed that is not a number' => [
        fn () => null,
        fn (ProjectKeyType $pair) => ['PUT', "api/v1/admin/projects/{$pair->project_id}/key-types", ['types' => [['code' => 'ADR', 'seed_sequence' => 'many']]]], 'types.0.seed_sequence',
        fn (ProjectKeyType $pair) => ['PUT', 'admin.projects.key-types.update', ['project' => $pair->project_id], ['types' => [
            'ADR' => ['enabled' => '1', 'seed_sequence' => 'many'],
        ]]], 'types.ADR.seed_sequence',
    ],
    'retired type' => [
        fn (ProjectKeyType $pair) => $pair->keyType->update(['is_active' => false]),
        fn (ProjectKeyType $pair) => ['PUT', "api/v1/admin/projects/{$pair->project_id}/key-types", ['types' => [['code' => 'ADR']]]], 'types.0.code',
        fn (ProjectKeyType $pair) => ['PUT', 'admin.projects.key-types.update', ['project' => $pair->project_id], ['types' => [
            'ADR' => ['enabled' => '1', 'seed_sequence' => ''],
        ]]], 'types.ADR.enabled',
    ],
    'type that is not registered' => [
        fn () => null,
        fn (ProjectKeyType $pair) => ['PUT', "api/v1/admin/projects/{$pair->project_id}/key-types", ['types' => [['code' => 'RFC']]]], 'types.0.code',
        fn (ProjectKeyType $pair) => ['PUT', 'admin.projects.key-types.update', ['project' => $pair->project_id], ['types' => [
            'RFC' => ['enabled' => '1'],
        ]]], 'types.RFC.enabled',
    ],
    'key type without a name' => [
        fn () => null,
        fn () => ['POST', 'api/v1/admin/key-types', ['code' => 'RFC']], 'name',
        fn () => ['POST', 'admin.key-types.store', [], ['code' => 'RFC']], 'name',
    ],
    'code registered in another case' => [
        fn () => null,
        fn () => ['POST', 'api/v1/admin/key-types', ['code' => 'adr', 'name' => 'Again']], 'code',
        fn () => ['POST', 'admin.key-types.store', [], ['code' => 'adr', 'name' => 'Again']], 'code',
    ],
    'empty key type name on update' => [
        fn () => null,
        fn (ProjectKeyType $pair) => ['PATCH', "api/v1/admin/key-types/{$pair->key_type_id}", ['name' => '']], 'name',
        fn (ProjectKeyType $pair) => ['PATCH', 'admin.key-types.update', ['keyType' => $pair->key_type_id], ['name' => '']], 'name',
    ],
]);
