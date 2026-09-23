<?php

use App\Models\Identifier;
use App\Models\KeyType;
use App\Models\Project;
use App\Models\ProjectKeyType;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

// contracts/rest-api.yaml: every request body is additionalProperties: false, and both PATCH bodies are minProperties: 1.

beforeEach(function () {
    Sanctum::actingAs(User::factory()->admin()->create());
    $this->pair = enabledPair();

    // Everything a refused request could have written.
    $this->registry = fn (): array => [
        Project::query()->get()->toArray(),
        KeyType::query()->get()->toArray(),
        ProjectKeyType::query()->get()->toArray(),
        Identifier::query()->get()->toArray(),
    ];
});

test('a field the contract does not declare is a validation error, and nothing is written', function (string $method, Closure $uri, array $body, string $field) {
    $before = ($this->registry)();

    $this->json($method, $uri($this->pair), $body)
        ->assertStatus(422)
        ->assertJsonStructure(['message', 'errors'])
        ->assertJsonValidationErrors([$field]);

    expect(($this->registry)())->toEqual($before);
})->with([
    'next id' => [
        'POST', fn () => 'api/v1/sequence/next',
        ['project_key' => 'gitlab.cas.ai/team/backend', 'type' => 'ADR', 'name' => 'add-oauth-auth', 'sequence_number' => 7],
        'sequence_number',
    ],
    'create project' => [
        'POST', fn () => 'api/v1/admin/projects',
        ['repo_url' => 'git@gitlab.cas.ai:team/frontend.git', 'name' => 'Frontend', 'is_active' => false],
        'is_active',
    ],
    'update project' => [
        'PATCH', fn (ProjectKeyType $pair) => "api/v1/admin/projects/{$pair->project_id}",
        ['name' => 'Renamed', 'key' => 'gitlab.cas.ai/team/other'],
        'key',
    ],
    'set key types' => [
        'PUT', fn (ProjectKeyType $pair) => "api/v1/admin/projects/{$pair->project_id}/key-types",
        ['types' => [['code' => 'ADR', 'seed_sequence' => 5]], 'mode' => 'merge'],
        'mode',
    ],
    'set key types, inside an item' => [
        'PUT', fn (ProjectKeyType $pair) => "api/v1/admin/projects/{$pair->project_id}/key-types",
        ['types' => [['code' => 'ADR', 'last_sequence' => 5]]],
        'types.0.last_sequence',
    ],
    'create key type' => [
        'POST', fn () => 'api/v1/admin/key-types',
        ['code' => 'RFC', 'name' => 'RFC', 'is_active' => false],
        'is_active',
    ],
    // Spec 004, FR-006: the template left the contract, so it is refused like any other undeclared field.
    'create key type with a template' => [
        'POST', fn () => 'api/v1/admin/key-types',
        ['code' => 'RFC', 'name' => 'RFC', 'format_template' => 'RFC-{number}'],
        'format_template',
    ],
    'update key type with a template' => [
        'PATCH', fn (ProjectKeyType $pair) => "api/v1/admin/key-types/{$pair->key_type_id}",
        ['name' => 'Decision', 'format_template' => 'ADR-{number:03d}'],
        'format_template',
    ],
    'update key type' => [
        'PATCH', fn (ProjectKeyType $pair) => "api/v1/admin/key-types/{$pair->key_type_id}",
        ['name' => 'Decision', 'code' => 'DEC'],
        'code',
    ],
]);

test('an empty update is a validation error, and nothing is written', function (Closure $uri) {
    $before = ($this->registry)();

    $this->patchJson($uri($this->pair), [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['body']);

    expect(($this->registry)())->toEqual($before);
})->with([
    'project' => fn (ProjectKeyType $pair) => "api/v1/admin/projects/{$pair->project_id}",
    'key type' => fn (ProjectKeyType $pair) => "api/v1/admin/key-types/{$pair->key_type_id}",
]);

// The contract closes request bodies, not query strings, so a read with an extra parameter is still answered.
test('a read ignores a query parameter the contract does not name', function () {
    $this->getJson('api/v1/sequence/list?'.http_build_query(['project_key' => 'gitlab.cas.ai/team/backend', 'type' => 'ADR', 'page' => 2]))
        ->assertOk();
});
