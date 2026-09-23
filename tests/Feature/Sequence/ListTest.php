<?php

use App\Models\Project;
use App\Models\ProjectKeyType;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Sanctum::actingAs(User::factory()->create());

    $this->adr = enabledPair();
    $this->next = fn (string $name, string $type = 'ADR', string $projectKey = 'gitlab.cas.ai/team/backend') => $this->postJson(
        'api/v1/sequence/next',
        ['project_key' => $projectKey, 'type' => $type, 'name' => $name],
    )->assertOk();
    $this->list = fn (array $query = []) => $this->getJson('api/v1/sequence/list?'.http_build_query([
        'project_key' => 'gitlab.cas.ai/team/backend', 'type' => 'ADR', ...$query,
    ]));
});

// Spec 004, FR-001: exact items, so a formatted name coming back fails here.
test('the issued identifiers of a pair are listed newest first, with the first wording and no formatted name', function () {
    $this->travelTo('2026-09-18 10:00:00');
    ($this->next)('init-project');
    $this->travelTo('2026-09-19 14:30:00');
    ($this->next)('Add Docker Support');
    ($this->next)('add_docker_support');

    ($this->list)()
        ->assertOk()
        ->assertExactJson([
            'project_key' => 'gitlab.cas.ai/team/backend',
            'type' => 'ADR',
            'items' => [
                ['sequence_number' => 2, 'name' => 'Add Docker Support', 'created_at' => '2026-09-19T14:30:00Z'],
                ['sequence_number' => 1, 'name' => 'init-project', 'created_at' => '2026-09-18T10:00:00Z'],
            ],
        ]);
});

test('the list holds only its own pair', function () {
    enabledPair($this->adr->project, ['code' => 'spec']);
    enabledPair(Project::factory()->create(['repo_url' => 'git@gitlab.cas.ai:team/frontend.git']), $this->adr->keyType);

    ($this->next)('backend-adr');
    ($this->next)('backend-spec', 'spec');
    ($this->next)('frontend-adr', 'ADR', 'gitlab.cas.ai/team/frontend');

    expect(($this->list)()->json('items.*.name'))->toBe(['backend-adr'])
        ->and(($this->list)(['type' => 'spec'])->json('items.*.name'))->toBe(['backend-spec']);
});

test('a pair with nothing issued lists nothing', function () {
    ($this->list)()->assertOk()->assertJsonPath('items', []);
});

test('the project key is normalized before lookup', function () {
    ($this->next)('init-project');

    ($this->list)(['project_key' => 'git@gitlab.cas.ai:Team/Backend.git'])
        ->assertOk()
        ->assertJsonPath('project_key', 'gitlab.cas.ai/team/backend')
        ->assertJsonCount(1, 'items');
});

// Edge Cases: retiring stops new numbers, never the reading of issued ones.
test('the list stays readable after the pair is retired', function (Closure $retire) {
    ($this->next)('init-project');

    $retire($this->adr);

    ($this->list)()->assertOk()->assertJsonCount(1, 'items');
})->with([
    'project retired' => [fn (ProjectKeyType $pair) => $pair->project->update(['is_active' => false])],
    'type disabled in the project' => [fn (ProjectKeyType $pair) => $pair->update(['is_enabled' => false])],
    'type retired' => [fn (ProjectKeyType $pair) => $pair->keyType->update(['is_active' => false])],
]);

test('an unknown project or a type never enabled in it is refused', function (array $query, array $error) {
    ($this->list)($query)->assertStatus(422)->assertJsonPath('error', $error);
})->with([
    'project not registered' => [
        ['project_key' => 'git@gitlab.cas.ai:team/sandbox.git'],
        ['code' => 'project_not_registered', 'project_key' => 'gitlab.cas.ai/team/sandbox'],
    ],
    'type never enabled' => [
        ['type' => 'RFC'],
        ['code' => 'type_not_enabled', 'project_key' => 'gitlab.cas.ai/team/backend', 'type' => 'RFC'],
    ],
]);

test('both query parameters are required', function () {
    $this->getJson('api/v1/sequence/list')
        ->assertStatus(422)
        ->assertJsonValidationErrors(['project_key', 'type']);
});
