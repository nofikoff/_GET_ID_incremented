<?php

use App\Models\Identifier;
use App\Models\Project;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->user = User::factory()->create();
    Sanctum::actingAs($this->user);

    $this->adr = enabledPair();
    $this->next = fn (string $name, string $type = 'ADR', string $projectKey = 'gitlab.cas.ai/team/backend') => $this->postJson(
        'api/v1/sequence/next',
        ['project_key' => $projectKey, 'type' => $type, 'name' => $name],
    );
});

// Spec 004, FR-001/FR-002: the bare number, no formatted name — the consumer builds the document name.
test('the first request for a theme gets number 1 and no formatted name', function () {
    $this->travelTo('2026-09-20 14:30:00');

    ($this->next)('add-oauth-auth')
        ->assertOk()
        ->assertExactJson([
            'project_key' => 'gitlab.cas.ai/team/backend',
            'type' => 'ADR',
            'name' => 'add-oauth-auth',
            'sequence_number' => 1,
            'is_new' => true,
            'created_at' => '2026-09-20T14:30:00Z',
        ]);
});

test('each new theme takes the next number', function () {
    expect(($this->next)('init-project')->json('sequence_number'))->toBe(1)
        ->and(($this->next)('add-docker-support')->json('sequence_number'))->toBe(2)
        ->and(($this->next)('add-oauth-auth')->json('sequence_number'))->toBe(3);
});

test('the response keeps the wording of the theme', function () {
    enabledPair($this->adr->project, ['code' => 'spec']);

    ($this->next)('Add OAuth Auth', 'spec')
        ->assertOk()
        ->assertJsonPath('sequence_number', 1)
        ->assertJsonPath('name', 'Add OAuth Auth');
});

test('numbering is kept per project and key type pair', function () {
    $other = Project::factory()->create(['repo_url' => 'git@gitlab.cas.ai:team/frontend.git']);
    enabledPair($other, $this->adr->keyType);
    enabledPair($this->adr->project, ['code' => 'spec']);

    ($this->next)('first-adr');
    ($this->next)('second-adr');

    expect(($this->next)('first-spec', 'spec')->json('sequence_number'))->toBe(1)
        ->and(($this->next)('first-adr', 'ADR', 'gitlab.cas.ai/team/frontend')->json('sequence_number'))->toBe(1)
        ->and($this->adr->refresh()->last_sequence)->toBe(2);
});

// FR-008a: the key goes through the same normalization as an origin, so a client may send either.
test('the project key is normalized before lookup', function (string $projectKey) {
    ($this->next)('add-oauth-auth', 'ADR', $projectKey)
        ->assertOk()
        ->assertJsonPath('project_key', 'gitlab.cas.ai/team/backend')
        ->assertJsonPath('sequence_number', 1);
})->with([
    'ssh origin' => 'git@gitlab.cas.ai:team/backend.git',
    'https origin' => 'https://gitlab.cas.ai/team/backend',
    'other case' => 'GitLab.cas.ai/Team/Backend',
]);

test('the registry records the wording, its slug and the author, and the counter advances', function () {
    ($this->next)('Add OAuth Auth')->assertOk();

    $identifier = Identifier::query()->sole();

    expect($identifier->name)->toBe('Add OAuth Auth')
        ->and($identifier->name_slug)->toBe('add-oauth-auth')
        ->and($identifier->sequence_number)->toBe(1)
        ->and($identifier->project_id)->toBe($this->adr->project_id)
        ->and($identifier->key_type_id)->toBe($this->adr->key_type_id)
        ->and($identifier->creator->is($this->user))->toBeTrue()
        ->and($this->adr->refresh()->last_sequence)->toBe(1);
});
