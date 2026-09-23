<?php

use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;

// Edge Cases, specs/002-admin-web-console/research.md R8: two administrators register one repository at once.
// The loser's INSERT hits the unique index after its own validation passed, and must read as an ordinary duplicate.

test('a duplicate key from a concurrent insert returns the form with the error under the address, not a crash', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    Project::creating(function (Project $project) use ($admin): void {
        DB::table('projects')->insert([
            'key' => $project->key,
            'name' => 'Racer',
            'repo_url' => 'git@gitlab.cas.ai:team/backend.git',
            'is_active' => true,
            'created_by' => $admin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    });

    $this->from(route('admin.projects.create'))
        ->post(route('admin.projects.store'), ['repo_url' => 'https://gitlab.cas.ai/team/backend', 'name' => 'Backend'])
        ->assertRedirect(route('admin.projects.create'))
        ->assertSessionHasErrors(['repo_url' => 'Проект с ключом «gitlab.cas.ai/team/backend» уже зарегистрирован.'])
        ->assertSessionHasInput('name', 'Backend');

    expect(Project::query()->sole()->name)->toBe('Racer');
});
