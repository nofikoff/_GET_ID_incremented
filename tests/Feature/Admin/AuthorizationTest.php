<?php

use App\Models\KeyType;
use App\Models\Project;
use App\Models\ProjectKeyType;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->project = Project::factory()->create(['repo_url' => 'git@gitlab.cas.ai:team/secret.git', 'name' => 'Secret']);
    $this->keyType = KeyType::factory()->create(['code' => 'ADR']);

    // Every administrative operation, with bodies an administrator would get accepted.
    $this->operations = fn (int $projectId, int $keyTypeId): array => [
        ['GET', 'api/v1/admin/projects', []],
        ['POST', 'api/v1/admin/projects', ['repo_url' => 'git@gitlab.cas.ai:team/new.git', 'name' => 'New']],
        ['PATCH', "api/v1/admin/projects/{$projectId}", ['name' => 'Renamed']],
        ['PUT', "api/v1/admin/projects/{$projectId}/key-types", ['types' => [['code' => 'ADR']]]],
        ['GET', 'api/v1/admin/key-types', []],
        ['POST', 'api/v1/admin/key-types', ['code' => 'RFC', 'name' => 'RFC', 'format_template' => 'RFC-{number}']],
        ['PATCH', "api/v1/admin/key-types/{$keyTypeId}", ['name' => 'Renamed']],
    ];
});

test('a member is refused every administrative operation and changes nothing', function () {
    Sanctum::actingAs(User::factory()->create());

    foreach (($this->operations)($this->project->id, $this->keyType->id) as [$method, $uri, $body]) {
        $response = $this->json($method, $uri, $body)
            ->assertForbidden()
            ->assertJsonPath('error.code', 'forbidden');

        expect($response->getContent())->not->toContain('gitlab.cas.ai/team/secret')->not->toContain('Secret');
    }

    expect(Project::query()->pluck('name')->all())->toBe(['Secret'])
        ->and(KeyType::query()->pluck('code')->all())->toBe(['ADR'])
        ->and(ProjectKeyType::query()->count())->toBe(0);
});

// FR-017: the role is checked before the lookup, so the answer never tells a member whether an id exists.
test('a member gets the same refusal for an id that does not exist', function () {
    Sanctum::actingAs(User::factory()->create());

    $answers = fn (array $operations) => collect($operations)->map(function (array $operation): array {
        $response = $this->json(...$operation);

        return [$response->status(), $response->json()];
    });

    $existing = $answers(($this->operations)($this->project->id, $this->keyType->id));
    $missing = $answers(($this->operations)(999999, 999999));

    expect($missing->all())->toBe($existing->all())
        ->and($missing->pluck(0)->unique()->all())->toBe([403]);
});

test('a member is refused before the body is validated', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->postJson('api/v1/admin/projects', [])->assertForbidden();
    $this->putJson("api/v1/admin/projects/{$this->project->id}/key-types", ['types' => 'none'])->assertForbidden();
});

test('an administrator is let through the same operations', function () {
    Sanctum::actingAs(User::factory()->admin()->create());

    foreach (($this->operations)($this->project->id, $this->keyType->id) as [$method, $uri, $body]) {
        expect($this->json($method, $uri, $body)->status())->toBeIn([200, 201], "{$method} {$uri}");
    }
});
