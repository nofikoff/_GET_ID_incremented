<?php

use App\Models\KeyType;
use App\Models\Project;
use App\Models\ProjectKeyType;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Sanctum::actingAs(User::factory()->admin()->create());

    $this->project = Project::factory()->create(['repo_url' => 'git@gitlab.cas.ai:team/backend.git']);
    $this->adr = KeyType::factory()->create(['code' => 'ADR', 'name' => 'Architecture Decision Record', 'format_template' => 'ADR-{number:04d}']);
    $this->spec = KeyType::factory()->create(['code' => 'spec', 'name' => 'Specification', 'format_template' => '{number:03d}-{name}']);

    $this->setTypes = fn (array $types) => $this->putJson("api/v1/admin/projects/{$this->project->id}/key-types", ['types' => $types]);
    $this->next = fn (string $name, string $type = 'ADR') => $this->postJson(
        'api/v1/sequence/next',
        ['project_key' => 'gitlab.cas.ai/team/backend', 'type' => $type, 'name' => $name],
    );
    $this->pair = fn (KeyType $type) => ProjectKeyType::query()
        ->where('project_id', $this->project->id)
        ->where('key_type_id', $type->id)
        ->first();
});

test('an administrator enables types in a project, one of them seeded', function () {
    ($this->setTypes)([['code' => 'ADR', 'seed_sequence' => 42], ['code' => 'spec']])
        ->assertOk()
        ->assertExactJson(['data' => [
            ['code' => 'ADR', 'name' => 'Architecture Decision Record', 'seed_sequence' => 42, 'last_sequence' => 0, 'next_number' => 43],
            ['code' => 'spec', 'name' => 'Specification', 'seed_sequence' => 0, 'last_sequence' => 0, 'next_number' => 1],
        ]]);

    ($this->next)('add-oauth-auth')->assertOk()->assertJsonPath('formatted_id', 'ADR-0043');
    ($this->next)('add-oauth-auth', 'spec')->assertOk()->assertJsonPath('formatted_id', '001-add-oauth-auth');
});

// FR-016: the counter lives on the link row, so dropping a type from the set disables it and never deletes it.
test('the set is replaced whole, and a dropped type is disabled with its counter kept', function () {
    ($this->setTypes)([['code' => 'ADR'], ['code' => 'spec']])->assertOk();
    ($this->next)('first')->assertOk();

    ($this->setTypes)([['code' => 'spec']])
        ->assertOk()
        ->assertJsonPath('data.*.code', ['spec']);

    expect(($this->pair)($this->adr))
        ->not->toBeNull()
        ->is_enabled->toBeFalse()
        ->last_sequence->toBe(1);
    ($this->next)('second')->assertStatus(422)->assertJsonPath('error.code', 'type_not_enabled');
});

test('a type enabled again continues its numbering where it stopped', function () {
    ($this->setTypes)([['code' => 'ADR']])->assertOk();
    ($this->next)('first');
    ($this->next)('second');

    ($this->setTypes)([])->assertOk()->assertJsonPath('data', []);
    ($this->setTypes)([['code' => 'ADR']])
        ->assertOk()
        ->assertJsonPath('data.0.next_number', 3);

    ($this->next)('third')->assertOk()->assertJsonPath('sequence_number', 3);
    expect(ProjectKeyType::query()->count())->toBe(1);
});

// FR-014b: a seed below an issued number would make the next issuance repeat it.
test('a seed below the last issued number is refused and changes nothing', function () {
    ($this->setTypes)([['code' => 'ADR'], ['code' => 'spec']])->assertOk();
    foreach (['a', 'b', 'c'] as $name) {
        ($this->next)($name);
    }

    ($this->setTypes)([['code' => 'spec', 'seed_sequence' => 5], ['code' => 'ADR', 'seed_sequence' => 2]])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['types.1.seed_sequence']);

    expect(($this->pair)($this->adr))->seed_sequence->toBe(0)->is_enabled->toBeTrue()
        ->and(($this->pair)($this->spec))->seed_sequence->toBe(0);
});

test('a seed at or above the last issued number is accepted', function (int $seed, int $next) {
    ($this->setTypes)([['code' => 'ADR']])->assertOk();
    foreach (['a', 'b', 'c'] as $name) {
        ($this->next)($name);
    }

    ($this->setTypes)([['code' => 'ADR', 'seed_sequence' => $seed]])
        ->assertOk()
        ->assertJsonPath('data.0.next_number', $next);

    ($this->next)('d')->assertJsonPath('sequence_number', $next);
})->with([
    'equal to the last issued' => [3, 4],
    'raised past it' => [100, 101],
]);

test('a type sent without a seed keeps the seed it had', function () {
    ($this->setTypes)([['code' => 'ADR', 'seed_sequence' => 42]])->assertOk();

    ($this->setTypes)([['code' => 'ADR']])
        ->assertOk()
        ->assertJsonPath('data.0.seed_sequence', 42)
        ->assertJsonPath('data.0.next_number', 43);
});

// Edge Cases: a type retired globally cannot be enabled in any project.
test('a retired type cannot be enabled', function () {
    $this->adr->update(['is_active' => false]);

    ($this->setTypes)([['code' => 'spec'], ['code' => 'ADR']])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['types.1.code']);

    expect(ProjectKeyType::query()->count())->toBe(0);
});

test('the set is validated', function (array $body, array $fields) {
    $this->putJson("api/v1/admin/projects/{$this->project->id}/key-types", $body)
        ->assertStatus(422)
        ->assertJsonValidationErrors($fields);

    expect(ProjectKeyType::query()->count())->toBe(0);
})->with([
    'no set' => [[], ['types']],
    'unknown code' => [['types' => [['code' => 'RFC']]], ['types.0.code']],
    'same code twice' => [['types' => [['code' => 'ADR'], ['code' => 'ADR']]], ['types.0.code', 'types.1.code']],
    'negative seed' => [['types' => [['code' => 'ADR', 'seed_sequence' => -1]]], ['types.0.seed_sequence']],
    'seed not a number' => [['types' => [['code' => 'ADR', 'seed_sequence' => 'many']]], ['types.0.seed_sequence']],
]);

test('an administrator gets a not_found for a project that does not exist', function () {
    ($this->setTypes)([['code' => 'ADR']])->assertOk();

    $this->putJson('api/v1/admin/projects/999999/key-types', ['types' => [['code' => 'ADR']]])
        ->assertNotFound()
        ->assertJsonPath('error.code', 'not_found');
});
