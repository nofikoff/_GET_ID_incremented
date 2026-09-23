<?php

use App\Models\Project;
use App\Models\User;

// FR-001–FR-005: a project is registered, changed, retired and returned in the browser, never deleted.

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->actingAs($this->admin);

    $this->store = fn (array $input) => $this->from(route('admin.projects.create'))->post(route('admin.projects.store'), $input);
    $this->update = fn (Project $project, array $input) => $this->from(route('admin.projects.show', $project))
        ->patch(route('admin.projects.update', $project), $input);
});

test('an administrator registers a project and its key is derived from the address', function () {
    $response = ($this->store)(['repo_url' => 'git@gitlab.cas.ai:Team/Backend.git', 'name' => 'Backend', 'description' => 'Main API']);

    $project = Project::query()->sole();
    $response->assertRedirect(route('admin.projects.show', $project))->assertSessionHas('status');
    expect($project)
        ->key->toBe('gitlab.cas.ai/team/backend')
        ->repo_url->toBe('git@gitlab.cas.ai:Team/Backend.git')
        ->name->toBe('Backend')
        ->description->toBe('Main API')
        ->is_active->toBeTrue()
        ->and($project->creator->is($this->admin))->toBeTrue();
});

test('the key is not taken from the form', function () {
    ($this->store)(['repo_url' => 'https://gitlab.cas.ai/team/backend', 'name' => 'Backend', 'key' => 'something/else']);

    expect(Project::query()->sole()->key)->toBe('gitlab.cas.ai/team/backend');
});

test('another address form of a registered repository returns the form with the error and the input', function (string $repoUrl) {
    Project::factory()->create(['repo_url' => 'git@gitlab.cas.ai:team/backend.git']);

    ($this->store)(['repo_url' => $repoUrl, 'name' => 'Duplicate', 'description' => 'Second try'])
        ->assertRedirect(route('admin.projects.create'))
        ->assertSessionHasErrors(['repo_url' => 'Проект с ключом «gitlab.cas.ai/team/backend» уже зарегистрирован.'])
        ->assertSessionHasInput(['repo_url' => $repoUrl, 'name' => 'Duplicate', 'description' => 'Second try']);

    expect(Project::query()->count())->toBe(1);
})->with([
    'same address' => 'git@gitlab.cas.ai:team/backend.git',
    'https form' => 'https://gitlab.cas.ai/team/backend',
    'other case' => 'ssh://git@GitLab.cas.ai:2222/Team/Backend.git',
]);

test('an address that does not parse returns the form with the reason under the address', function () {
    ($this->store)(['repo_url' => 'not a repository', 'name' => 'Broken'])
        ->assertRedirect(route('admin.projects.create'))
        ->assertSessionHasErrors(['repo_url' => 'не разобран'])
        ->assertSessionHasInput('name', 'Broken');

    expect(Project::query()->count())->toBe(0);
});

test('the address and the name are required', function () {
    ($this->store)([])->assertSessionHasErrors(['repo_url', 'name']);

    expect(Project::query()->count())->toBe(0);
});

test('an administrator renames and describes a project, and its key and address stay', function () {
    $project = Project::factory()->create(['repo_url' => 'git@gitlab.cas.ai:team/backend.git', 'name' => 'Backend']);

    ($this->update)($project, ['name' => 'Backend API', 'description' => 'Renamed'])
        ->assertRedirect(route('admin.projects.show', $project))
        ->assertSessionHasNoErrors()
        ->assertSessionHas('status');

    expect($project->refresh())
        ->name->toBe('Backend API')
        ->description->toBe('Renamed')
        ->key->toBe('gitlab.cas.ai/team/backend')
        ->repo_url->toBe('git@gitlab.cas.ai:team/backend.git');
});

test('the address is not taken by the update form', function () {
    $project = Project::factory()->create(['repo_url' => 'git@gitlab.cas.ai:team/backend.git', 'name' => 'Backend']);

    ($this->update)($project, ['name' => 'Backend', 'repo_url' => 'git@gitlab.cas.ai:team/other.git', 'key' => 'gitlab.cas.ai/team/other']);

    expect($project->refresh())
        ->key->toBe('gitlab.cas.ai/team/backend')
        ->repo_url->toBe('git@gitlab.cas.ai:team/backend.git');
});

test('clearing the description removes it', function () {
    $project = Project::factory()->create(['name' => 'Backend', 'description' => 'Main API']);

    ($this->update)($project, ['name' => 'Backend', 'description' => ''])->assertSessionHasNoErrors();

    expect($project->refresh()->description)->toBeNull();
});

test('an update is validated and changes nothing when refused', function () {
    $project = Project::factory()->create(['name' => 'Backend']);

    ($this->update)($project, ['name' => '', 'is_active' => 'maybe'])
        ->assertRedirect(route('admin.projects.show', $project))
        ->assertSessionHasErrors(['name', 'is_active']);

    expect($project->refresh())->name->toBe('Backend')->is_active->toBeTrue();
});

test('retiring a project and returning it', function () {
    $project = Project::factory()->create();

    ($this->update)($project, ['is_active' => '0'])->assertRedirect(route('admin.projects.show', $project))->assertSessionHasNoErrors();
    expect($project->refresh()->is_active)->toBeFalse();

    ($this->update)($project, ['is_active' => '1'])->assertSessionHasNoErrors();
    expect($project->refresh()->is_active)->toBeTrue()
        ->and(Project::query()->count())->toBe(1);
});

test('the list holds every project, retired ones included, and offers a new one', function () {
    Project::factory()->create(['repo_url' => 'git@gitlab.cas.ai:team/backend.git', 'name' => 'Backend']);
    $legacy = Project::factory()->inactive()->create(['repo_url' => 'git@gitlab.cas.ai:team/legacy.git', 'name' => 'Legacy']);

    $this->get(route('admin.projects.index'))
        ->assertOk()
        ->assertSeeInOrder(['gitlab.cas.ai/team/backend', 'Backend', 'gitlab.cas.ai/team/legacy', 'Legacy', 'выведен из обращения'])
        ->assertSee(route('admin.projects.create'))
        ->assertSee(route('admin.projects.show', $legacy))
        ->assertDontSee('через административный API');
});

test('the new project form carries its fields and its CSRF token', function () {
    $this->get(route('admin.projects.create'))
        ->assertOk()
        ->assertSee(route('admin.projects.store'))
        ->assertSee('name="repo_url"', false)
        ->assertSee('name="name"', false)
        ->assertSee('name="description"', false)
        ->assertSee('name="_token"', false)
        ->assertDontSee('name="key"', false);
});

test('the card shows the project and the forms that change it', function () {
    $project = Project::factory()->create([
        'repo_url' => 'git@gitlab.cas.ai:team/backend.git',
        'name' => 'Backend',
        'description' => 'Main API',
    ]);

    $this->get(route('admin.projects.show', $project))
        ->assertOk()
        ->assertSee('gitlab.cas.ai/team/backend')
        ->assertSee('git@gitlab.cas.ai:team/backend.git')
        ->assertSee('value="Backend"', false)
        ->assertSee('Main API')
        ->assertSee(route('admin.projects.update', $project))
        ->assertSee(route('admin.projects.key-types.update', $project))
        ->assertDontSee('name="repo_url"', false);
});

// FR-004: retiring stops issuance across the project, so it asks first; returning stops nothing, so it does not.
test('retiring asks for a confirmation and returning does not', function () {
    $active = Project::factory()->create();
    $retired = Project::factory()->inactive()->create();

    $this->get(route('admin.projects.show', $active))
        ->assertSee('onsubmit="return confirm(', false)
        ->assertSee('name="is_active" value="0"', false);

    $this->get(route('admin.projects.show', $retired))
        ->assertDontSee('onsubmit="return confirm(', false)
        ->assertSee('name="is_active" value="1"', false);
});
