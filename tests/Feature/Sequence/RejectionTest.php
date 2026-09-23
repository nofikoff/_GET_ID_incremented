<?php

use App\Models\Identifier;
use App\Models\KeyType;
use App\Models\Project;
use App\Models\ProjectKeyType;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Sanctum::actingAs(User::factory()->create());

    $this->adr = enabledPair();
    KeyType::factory()->create(['code' => 'spec', 'format_template' => '{number:03d}-{name}']);

    $this->next = fn (array $input) => $this->postJson('api/v1/sequence/next', [
        'project_key' => 'gitlab.cas.ai/team/backend', 'type' => 'ADR', 'name' => 'add-oauth-auth', ...$input,
    ]);
});

test('each refusal carries its own code and the normalized key, and issues nothing', function (Closure $arrange, array $input, array $error) {
    $arrange($this->adr);

    ($this->next)($input)
        ->assertStatus(422)
        ->assertJsonPath('error', $error)
        ->assertJsonStructure(['message', 'error' => ['code']]);

    expect(Identifier::query()->count())->toBe(0)
        ->and($this->adr->refresh()->last_sequence)->toBe(0);
})->with([
    'project not registered' => [
        fn () => null,
        ['project_key' => 'git@gitlab.cas.ai:team/sandbox.git'],
        ['code' => 'project_not_registered', 'project_key' => 'gitlab.cas.ai/team/sandbox'],
    ],
    'project retired' => [
        fn (ProjectKeyType $pair) => $pair->project->update(['is_active' => false]),
        [],
        ['code' => 'project_inactive', 'project_key' => 'gitlab.cas.ai/team/backend'],
    ],
    'type never enabled in the project' => [
        fn () => null,
        ['type' => 'spec'],
        ['code' => 'type_not_enabled', 'project_key' => 'gitlab.cas.ai/team/backend', 'type' => 'spec'],
    ],
    'type disabled in the project' => [
        fn (ProjectKeyType $pair) => $pair->update(['is_enabled' => false]),
        [],
        ['code' => 'type_not_enabled', 'project_key' => 'gitlab.cas.ai/team/backend', 'type' => 'ADR'],
    ],
    'no such type' => [
        fn () => null,
        ['type' => 'RFC'],
        ['code' => 'type_not_enabled', 'project_key' => 'gitlab.cas.ai/team/backend', 'type' => 'RFC'],
    ],
    'type retired' => [
        fn (ProjectKeyType $pair) => $pair->keyType->update(['is_active' => false]),
        [],
        ['code' => 'type_inactive', 'project_key' => 'gitlab.cas.ai/team/backend', 'type' => 'ADR'],
    ],
    'theme empty after normalization' => [
        fn () => null,
        ['name' => '--- ... ___'],
        ['code' => 'name_empty_after_normalization'],
    ],
    'project key not parsable' => [
        fn () => null,
        ['project_key' => 'not a repository'],
        ['code' => 'origin_unparsable'],
    ],
]);

test('the unregistered project refusal names the key and the next step', function () {
    expect(($this->next)(['project_key' => 'https://gitlab.cas.ai/team/sandbox.git'])->json('message'))
        ->toContain('gitlab.cas.ai/team/sandbox')
        ->toContain('администратор');
});

// SC-003: a refusal for an unregistered project always carries the key, whatever else is wrong with the request.
test('an unregistered project is reported before an empty theme', function () {
    ($this->next)(['project_key' => 'gitlab.cas.ai/team/sandbox', 'name' => '...'])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'project_not_registered')
        ->assertJsonPath('error.project_key', 'gitlab.cas.ai/team/sandbox');
});

// FR-011: asking about a project never registers it.
test('a refused project is not registered by the request', function () {
    ($this->next)(['project_key' => 'gitlab.cas.ai/team/sandbox'])->assertStatus(422);

    expect(Project::query()->pluck('key')->all())->toBe(['gitlab.cas.ai/team/backend']);
});

test('missing or oversized fields are validation errors', function (array $input, array $fields) {
    $this->postJson('api/v1/sequence/next', $input)
        ->assertStatus(422)
        ->assertJsonValidationErrors($fields);
})->with([
    'nothing sent' => [[], ['project_key', 'type', 'name']],
    'not strings' => [['project_key' => ['a'], 'type' => 1, 'name' => ['b']], ['project_key', 'type', 'name']],
    'too long' => [
        ['project_key' => str_repeat('a', 256), 'type' => str_repeat('T', 33), 'name' => str_repeat('n', 256)],
        ['project_key', 'type', 'name'],
    ],
]);
