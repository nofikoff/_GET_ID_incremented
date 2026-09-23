<?php

use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    Sanctum::actingAs($this->admin);
});

test('an administrator registers a project and its key is derived from the address', function () {
    $this->travelTo('2026-09-20 14:30:00');

    $response = $this->postJson('api/v1/admin/projects', [
        'repo_url' => 'git@gitlab.cas.ai:Team/Backend.git',
        'name' => 'Backend',
        'description' => 'Main API',
    ]);

    $project = Project::query()->sole();
    $response->assertCreated()->assertExactJson([
        'id' => $project->id,
        'key' => 'gitlab.cas.ai/team/backend',
        'name' => 'Backend',
        'repo_url' => 'git@gitlab.cas.ai:Team/Backend.git',
        'description' => 'Main API',
        'is_active' => true,
        'created_at' => '2026-09-20T14:30:00Z',
    ]);
    expect($project->creator->is($this->admin))->toBeTrue();
});

test('the key cannot be set by hand', function () {
    $this->postJson('api/v1/admin/projects', [
        'repo_url' => 'https://gitlab.cas.ai/team/backend',
        'name' => 'Backend',
        'key' => 'something/else',
    ])->assertStatus(422)->assertJsonValidationErrors(['key']);

    expect(Project::query()->count())->toBe(0);
});

test('another address form of a registered repository is a validation error', function (string $repoUrl) {
    Project::factory()->create(['repo_url' => 'git@gitlab.cas.ai:team/backend.git']);

    $this->postJson('api/v1/admin/projects', ['repo_url' => $repoUrl, 'name' => 'Duplicate'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['repo_url']);

    expect(Project::query()->count())->toBe(1);
})->with([
    'same address' => 'git@gitlab.cas.ai:team/backend.git',
    'https form' => 'https://gitlab.cas.ai/team/backend',
    'other case' => 'ssh://git@GitLab.cas.ai:2222/Team/Backend.git',
]);

// A racing insert lands between validation's SELECT and this request's own INSERT: the unique index
// still refuses the second row, and that refusal must reach the client as the rule's own 422, not a 500.
test('a duplicate key from a concurrent insert is a validation error, not a crash', function () {
    $racerId = $this->admin->id;
    Project::creating(function (Project $project) use ($racerId): void {
        DB::table('projects')->insert([
            'key' => $project->key,
            'name' => 'Racer',
            'repo_url' => 'git@gitlab.cas.ai:team/backend.git',
            'is_active' => true,
            'created_by' => $racerId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    });

    $this->postJson('api/v1/admin/projects', ['repo_url' => 'git@gitlab.cas.ai:team/backend.git', 'name' => 'Backend'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['repo_url' => 'уже зарегистрирован']);

    expect(Project::query()->count())->toBe(1);
});

test('an address that does not parse is a validation error that says why', function () {
    $this->postJson('api/v1/admin/projects', ['repo_url' => 'not a repository', 'name' => 'Broken'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['repo_url' => 'не разобран']);

    expect(Project::query()->count())->toBe(0);
});

test('the address and the name are required', function () {
    $this->postJson('api/v1/admin/projects', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['repo_url', 'name']);
});

test('an administrator renames a project, and its key and address stay', function () {
    $project = Project::factory()->create(['repo_url' => 'git@gitlab.cas.ai:team/backend.git', 'name' => 'Backend']);

    $this->patchJson("api/v1/admin/projects/{$project->id}", ['name' => 'Backend API', 'description' => 'Renamed'])
        ->assertOk()
        ->assertJsonPath('name', 'Backend API')
        ->assertJsonPath('description', 'Renamed')
        ->assertJsonPath('key', 'gitlab.cas.ai/team/backend')
        ->assertJsonPath('repo_url', 'git@gitlab.cas.ai:team/backend.git');

    $this->patchJson("api/v1/admin/projects/{$project->id}", ['repo_url' => 'git@gitlab.cas.ai:team/other.git'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['repo_url']);

    expect($project->refresh())
        ->key->toBe('gitlab.cas.ai/team/backend')
        ->repo_url->toBe('git@gitlab.cas.ai:team/backend.git');
});

test('retiring a project stops issuance, and returning it resumes the numbering', function () {
    $pair = enabledPair();
    $next = fn (string $name) => $this->postJson(
        'api/v1/sequence/next',
        ['project_key' => 'gitlab.cas.ai/team/backend', 'type' => 'ADR', 'name' => $name],
    );
    $next('first')->assertOk();

    $this->patchJson("api/v1/admin/projects/{$pair->project_id}", ['is_active' => false])
        ->assertOk()
        ->assertJsonPath('is_active', false);
    $next('second')->assertStatus(422)->assertJsonPath('error.code', 'project_inactive');

    $this->patchJson("api/v1/admin/projects/{$pair->project_id}", ['is_active' => true])->assertOk();
    $next('second')->assertOk()->assertJsonPath('sequence_number', 2);
});

test('an update is validated', function () {
    $project = Project::factory()->create();

    $this->patchJson("api/v1/admin/projects/{$project->id}", ['name' => '', 'is_active' => 'maybe'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['name', 'is_active']);
});

test('the list holds every project, retired ones included', function () {
    Project::factory()->create(['repo_url' => 'git@gitlab.cas.ai:team/backend.git']);
    Project::factory()->inactive()->create(['repo_url' => 'git@gitlab.cas.ai:team/legacy.git']);

    $this->getJson('api/v1/admin/projects')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonStructure(['data' => [['id', 'key', 'name', 'repo_url', 'description', 'is_active', 'created_at']]])
        ->assertJsonPath('data.*.key', ['gitlab.cas.ai/team/backend', 'gitlab.cas.ai/team/legacy']);
});

test('an administrator gets a not_found for a project that does not exist', function () {
    $project = Project::factory()->create();
    $this->patchJson("api/v1/admin/projects/{$project->id}", ['name' => 'Real'])->assertOk();

    $this->patchJson('api/v1/admin/projects/999999', ['name' => 'Ghost'])
        ->assertNotFound()
        ->assertJsonPath('error.code', 'not_found');
});
