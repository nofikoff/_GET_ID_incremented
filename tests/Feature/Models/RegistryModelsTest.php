<?php

use App\Models\ApiLog;
use App\Models\Identifier;
use App\Models\KeyType;
use App\Models\Project;
use App\Models\ProjectKeyType;
use App\Models\User;
use Laravel\Sanctum\NewAccessToken;

beforeEach(function () {
    $this->user = User::factory()->create();

    $this->project = new Project(['key' => 'gitlab.cas.ai/team/backend', 'name' => 'Backend', 'repo_url' => 'git@gitlab.cas.ai:team/backend.git']);
    $this->project->creator()->associate($this->user)->save();

    $this->adr = KeyType::create(['code' => 'ADR', 'name' => 'Architecture Decision Record', 'format_template' => 'ADR-{number:04d}']);
});

test('active users exclude the deactivated', function () {
    $gone = User::factory()->create();
    $gone->forceFill(['deactivated_at' => now()])->save();

    expect(User::active()->pluck('id')->all())->toBe([$this->user->id])
        ->and($gone->refresh()->deactivated_at)->toBeInstanceOf(DateTimeInterface::class);
});

test('a user holds several named api tokens', function () {
    expect($this->user->createToken('laptop'))->toBeInstanceOf(NewAccessToken::class);
    $this->user->createToken('ci');

    expect($this->user->tokens()->pluck('name')->sort()->values()->all())->toBe(['ci', 'laptop']);
});

test('active scopes skip retired projects and key types', function () {
    $retired = new Project(['key' => 'gitlab.cas.ai/team/old', 'name' => 'Old', 'repo_url' => 'https://gitlab.cas.ai/team/old', 'is_active' => false]);
    $retired->creator()->associate($this->user)->save();
    KeyType::create(['code' => 'RFC', 'name' => 'Request for comments', 'format_template' => 'RFC-{number}', 'is_active' => false]);

    expect(Project::active()->pluck('key')->all())->toBe(['gitlab.cas.ai/team/backend'])
        ->and(KeyType::active()->pluck('code')->all())->toBe(['ADR'])
        ->and($retired->is_active)->toBeFalse();
});

test('a project reaches its key types through the counter pivot', function () {
    $this->project->keyTypes()->attach($this->adr, ['seed_sequence' => 42]);

    $pivot = $this->project->keyTypes()->first()->pivot;

    expect($pivot)->toBeInstanceOf(ProjectKeyType::class)
        ->and($pivot->id)->toBeInt()
        ->and($pivot->seed_sequence)->toBe(42)
        ->and($pivot->last_sequence)->toBe(0)
        ->and($pivot->is_enabled)->toBeTrue()
        ->and($pivot->nextSequence())->toBe(43)
        ->and($this->adr->projects()->first()->is($this->project))->toBeTrue();
});

test('the counter row loads standalone with its project and key type', function () {
    $this->project->keyTypes()->attach($this->adr);

    $counter = ProjectKeyType::query()->sole();

    expect($counter->project->is($this->project))->toBeTrue()
        ->and($counter->keyType->is($this->adr))->toBeTrue();
});

test('an api log keeps a creation time only', function () {
    $log = ApiLog::create([
        'user_id' => $this->user->id, 'token_name' => 'laptop', 'method' => 'POST', 'endpoint' => 'api/v1/sequence/next',
        'payload' => ['type' => 'ADR'], 'status_code' => 200, 'duration_ms' => 12,
    ])->refresh();

    expect($log->created_at)->toBeInstanceOf(DateTimeInterface::class)
        ->and($log->payload)->toBe(['type' => 'ADR'])
        ->and($log->user->is($this->user))->toBeTrue()
        ->and(ApiLog::UPDATED_AT)->toBeNull();
});

test('an identifier belongs to its project, key type and author', function () {
    $identifier = new Identifier(['name' => 'Add OAuth', 'name_slug' => 'add-oauth', 'sequence_number' => 1]);
    $identifier->project()->associate($this->project);
    $identifier->keyType()->associate($this->adr);
    $identifier->creator()->associate($this->user);
    $identifier->save();

    expect($this->project->identifiers()->sole()->sequence_number)->toBe(1)
        ->and($identifier->keyType->is($this->adr))->toBeTrue()
        ->and($identifier->creator->is($this->user))->toBeTrue();
});
