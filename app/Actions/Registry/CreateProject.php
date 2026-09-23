<?php

namespace App\Actions\Registry;

use App\Domain\Project\ProjectKey;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Arr;

final class CreateProject
{
    public function __construct(private readonly RegistryChangeLog $changeLog) {}

    /**
     * @param  array<string, mixed>  $data  validated by ProjectRules::store()
     *
     * @throws UniqueConstraintViolationException when a concurrent registration of the same key won the race: the
     *                                            caller re-runs its request's rules for the refusal
     *                                            (specs/002-admin-web-console/research.md R8)
     */
    public function __invoke(User $admin, array $data): Project
    {
        $project = new Project(Arr::only($data, ['repo_url', 'name', 'description']));
        $project->key = ProjectKey::fromOrigin($project->repo_url)->value;
        $project->creator()->associate($admin);
        $project->save();

        $this->changeLog->created($admin, $project);

        return $project;
    }
}
