<?php

use App\Actions\Registry\WithdrawIdentifier;
use App\Domain\Sequence\SequenceIssuer;
use App\Models\Identifier;
use App\Models\KeyType;
use App\Models\Project;
use App\Models\ProjectKeyType;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\Sanctum;

// FR-019: every registry change leaves a trace in the application log — who, what and «было → стало».

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    Sanctum::actingAs($this->admin);
    Log::spy();

    $this->trace = fn (string $operation, string $entity, int $entityId, array $changes): array => [
        'admin_id' => $this->admin->id,
        'admin_email' => $this->admin->email,
        'operation' => $operation,
        'entity' => $entity,
        'entity_id' => $entityId,
        'changes' => $changes,
    ];
});

/**
 * @return ArrayObject<int, array<string, mixed>> filled with the context of each registry change as it is traced
 */
function registryTraces(): ArrayObject
{
    $traces = new ArrayObject;
    Log::shouldReceive('info')
        ->with('registry change', Mockery::type('array'))
        ->andReturnUsing(fn (string $message, array $context) => $traces->append($context));

    return $traces;
}

test('a project registered over REST is traced with the fields it was created with', function () {
    $traces = registryTraces();

    $this->postJson('api/v1/admin/projects', ['repo_url' => 'git@gitlab.cas.ai:team/backend.git', 'name' => 'Backend'])->assertCreated();

    expect($traces->getArrayCopy())->toBe([($this->trace)('create', 'project', Project::query()->sole()->id, [
        'key' => [null, 'gitlab.cas.ai/team/backend'],
        'name' => [null, 'Backend'],
        'repo_url' => [null, 'git@gitlab.cas.ai:team/backend.git'],
        'is_active' => [null, true],
    ])]);
});

test('a project update is traced with the changed fields only, before and after', function () {
    $project = Project::factory()->create(['name' => 'Backend', 'description' => 'Main API']);
    $traces = registryTraces();

    $this->patchJson("api/v1/admin/projects/{$project->id}", ['name' => 'Backend API', 'description' => 'Main API'])->assertOk();
    $this->patchJson("api/v1/admin/projects/{$project->id}", ['is_active' => false])->assertOk();

    expect($traces->getArrayCopy())->toBe([
        ($this->trace)('update', 'project', $project->id, ['name' => ['Backend', 'Backend API']]),
        ($this->trace)('update', 'project', $project->id, ['is_active' => [true, false]]),
    ]);
});

test('a key type registered and changed over REST is traced', function () {
    $traces = registryTraces();

    $this->postJson('api/v1/admin/key-types', ['code' => 'RFC', 'name' => 'RFC', 'format_template' => 'RFC-{number}'])->assertCreated();
    $keyType = KeyType::query()->sole();
    $this->patchJson("api/v1/admin/key-types/{$keyType->id}", ['format_template' => 'RFC-{number:03d}'])->assertOk();

    expect($traces->getArrayCopy())->toBe([
        ($this->trace)('create', 'key_type', $keyType->id, [
            'code' => [null, 'RFC'],
            'name' => [null, 'RFC'],
            'format_template' => [null, 'RFC-{number}'],
            'is_active' => [null, true],
        ]),
        ($this->trace)('update', 'key_type', $keyType->id, ['format_template' => ['RFC-{number}', 'RFC-{number:03d}']]),
    ]);
});

test('a set of key types is traced by the types it changed', function () {
    $pair = enabledPair(counter: ['seed_sequence' => 7]);
    KeyType::factory()->create(['code' => 'spec']);
    $traces = registryTraces();
    $setTypes = fn (array $types) => $this->putJson("api/v1/admin/projects/{$pair->project_id}/key-types", ['types' => $types])->assertOk();

    $setTypes([['code' => 'ADR'], ['code' => 'spec', 'seed_sequence' => 12]]);
    $setTypes([['code' => 'spec']]);

    expect($traces->getArrayCopy())->toBe([
        ($this->trace)('set_key_types', 'project', $pair->project_id, [
            'spec' => ['enabled' => [null, true], 'seed_sequence' => [null, 12]],
        ]),
        ($this->trace)('set_key_types', 'project', $pair->project_id, [
            'ADR' => ['enabled' => [true, false]],
        ]),
    ]);
});

test('a request that changes nothing leaves no trace', function () {
    $pair = enabledPair(project: ['name' => 'Backend'], counter: ['seed_sequence' => 7]);

    $this->patchJson("api/v1/admin/projects/{$pair->project_id}", ['name' => 'Backend'])->assertOk();
    $this->patchJson("api/v1/admin/key-types/{$pair->key_type_id}", ['format_template' => 'ADR-{number:04d}'])->assertOk();
    $this->putJson("api/v1/admin/projects/{$pair->project_id}/key-types", ['types' => [['code' => 'ADR', 'seed_sequence' => 7]]])->assertOk();

    Log::shouldNotHaveReceived('info');
});

test('a refused request leaves no trace', function () {
    $pair = enabledPair(counter: ['last_sequence' => 5]);

    $this->putJson("api/v1/admin/projects/{$pair->project_id}/key-types", ['types' => [['code' => 'ADR', 'seed_sequence' => 2]]])
        ->assertStatus(422);

    Log::shouldNotHaveReceived('info');
});

test('a trace that cannot be written is reported, and the change stands', function () {
    $failure = new RuntimeException('log channel is unavailable');
    Log::shouldReceive('info')->with('registry change', Mockery::type('array'))->andThrow($failure);

    $this->postJson('api/v1/admin/projects', ['repo_url' => 'git@gitlab.cas.ai:team/backend.git', 'name' => 'Backend'])
        ->assertCreated()
        ->assertJsonPath('key', 'gitlab.cas.ai/team/backend');

    expect(Project::query()->sole()->name)->toBe('Backend')
        ->and(ProjectKeyType::query()->count())->toBe(0);
    Log::shouldHaveReceived('error')->once()->withArgs(
        fn (string $message, array $context = []): bool => ($context['exception'] ?? null) === $failure,
    );
});

test('a withdrawn number is traced with its theme and the counter it rolled back', function () {
    $pair = enabledPair(counter: ['seed_sequence' => 31]);
    $issuer = app(SequenceIssuer::class);
    $issuer->issue('gitlab.cas.ai/team/backend', 'ADR', 'test-spec');
    $tail = Identifier::query()->where('sequence_number', $issuer->issue('gitlab.cas.ai/team/backend', 'ADR', 'test2')->sequenceNumber)->sole();
    $traces = registryTraces();

    app(WithdrawIdentifier::class)($this->admin, $tail);

    expect($traces->getArrayCopy())->toBe([($this->trace)('withdraw_identifier', 'project', $pair->project_id, [
        'ADR' => [
            'identifier' => ['ADR-0033', null],
            'name' => ['test2', null],
            'last_sequence' => [33, 32],
        ],
    ])]);
});

test('a withdrawal whose trace cannot be written still stands', function () {
    enabledPair();
    $issued = app(SequenceIssuer::class)->issue('gitlab.cas.ai/team/backend', 'ADR', 'test');
    Log::shouldReceive('info')->with('registry change', Mockery::type('array'))->andThrow(new RuntimeException('log channel is unavailable'));

    app(WithdrawIdentifier::class)($this->admin, Identifier::query()->where('sequence_number', $issued->sequenceNumber)->sole());

    expect(Identifier::query()->count())->toBe(0);
    Log::shouldHaveReceived('error')->once();
});
