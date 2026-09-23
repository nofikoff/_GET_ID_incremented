<?php

namespace App\Actions\Registry;

use App\Models\Project;
use App\Models\User;

final class UpdateProject
{
    public function __construct(private readonly RegistryChangeLog $changeLog) {}

    /**
     * @param  array<string, mixed>  $data  validated by ProjectRules::update()
     */
    public function __invoke(User $admin, Project $project, array $data): Project
    {
        $project->fill($data);

        if ($project->isDirty()) {
            $project->save();
            $this->changeLog->updated($admin, $project);
        }

        return $project;
    }
}
