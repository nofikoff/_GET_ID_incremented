<?php

use App\Models\KeyType;
use App\Models\Project;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Sanctum::actingAs(User::factory()->create());

    $this->resolve = fn (string $origin) => $this->getJson('api/v1/projects/resolve?'.http_build_query(['origin' => $origin]));
});

test('both address forms of a registered repository resolve to its key and enabled types', function (string $origin) {
    $pair = enabledPair(['name' => 'Backend'], ['name' => 'Architecture Decision Record'], ['last_sequence' => 12]);
    enabledPair($pair->project, ['code' => 'spec', 'name' => 'Specification', 'format_template' => '{number:03d}-{name}'], ['seed_sequence' => 3]);

    ($this->resolve)($origin)
        ->assertOk()
        ->assertExactJson([
            'project_key' => 'gitlab.cas.ai/team/backend',
            'registered' => true,
            'active' => true,
            'name' => 'Backend',
            'types' => [
                ['code' => 'ADR', 'name' => 'Architecture Decision Record', 'next_number' => 13],
                ['code' => 'spec', 'name' => 'Specification', 'next_number' => 4],
            ],
            'hint' => null,
        ]);
})->with([
    'ssh' => 'git@gitlab.cas.ai:team/backend.git',
    'https' => 'https://gitlab.cas.ai/team/backend',
]);

test('only types that can issue are listed', function () {
    $pair = enabledPair();
    enabledPair($pair->project, ['code' => 'spec'], ['is_enabled' => false]);
    enabledPair($pair->project, ['code' => 'RFC', 'is_active' => false]);
    KeyType::factory()->create(['code' => 'memo']);

    ($this->resolve)('git@gitlab.cas.ai:team/backend.git')->assertJsonPath('types.*.code', ['ADR']);
});

test('an unregistered repository resolves to its key, a refusal flag and what to do next', function () {
    $response = ($this->resolve)('git@gitlab.cas.ai:team/sandbox.git')
        ->assertOk()
        ->assertJson([
            'project_key' => 'gitlab.cas.ai/team/sandbox',
            'registered' => false,
            'active' => false,
            'name' => null,
            'types' => [],
        ]);

    expect($response->json('hint'))->toContain('администратор')->toContain('gitlab.cas.ai/team/sandbox')
        ->and(Project::query()->count())->toBe(0);
});

test('a retired project resolves as registered but inactive, with a hint', function () {
    enabledPair(['is_active' => false]);

    $response = ($this->resolve)('git@gitlab.cas.ai:team/backend.git')
        ->assertOk()
        ->assertJsonPath('registered', true)
        ->assertJsonPath('active', false);

    expect($response->json('hint'))->toBeString()->not->toBeEmpty();
});

// FR-009: the answer concerns the address asked about and nothing else.
test('resolving one address reveals no other project', function () {
    enabledPair(['repo_url' => 'git@gitlab.cas.ai:team/backend.git', 'name' => 'Backend']);
    enabledPair(['repo_url' => 'git@gitlab.cas.ai:team/secret.git', 'name' => 'Secret'], ['code' => 'spec']);

    foreach (['git@gitlab.cas.ai:team/backend.git', 'git@gitlab.cas.ai:team/nowhere.git'] as $origin) {
        expect(($this->resolve)($origin)->assertOk()->getContent())
            ->not->toContain('secret')
            ->not->toContain('Secret');
    }
});

test('an address that does not parse is refused with its own code', function () {
    ($this->resolve)('not a repository')
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'origin_unparsable');
});

test('the origin is required', function () {
    $this->getJson('api/v1/projects/resolve')
        ->assertStatus(422)
        ->assertJsonValidationErrors(['origin']);
});
