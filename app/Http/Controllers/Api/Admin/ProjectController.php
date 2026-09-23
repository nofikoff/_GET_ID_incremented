<?php

namespace App\Http\Controllers\Api\Admin;

use App\Actions\Registry\CreateProject;
use App\Actions\Registry\UpdateProject;
use App\Http\Concerns\RethrowsUniqueConflictAsValidation;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\StoreProjectRequest;
use App\Http\Requests\Api\Admin\UpdateProjectRequest;
use App\Http\Resources\ProjectResource;
use App\Models\Project;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class ProjectController extends Controller
{
    use RethrowsUniqueConflictAsValidation;

    public function index(): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Project::class);

        return ProjectResource::collection(Project::query()->orderBy('key')->get());
    }

    public function store(StoreProjectRequest $request, CreateProject $createProject, #[CurrentUser] User $admin): ProjectResource
    {
        try {
            $project = $createProject($admin, $request->validated());
        } catch (UniqueConstraintViolationException $conflict) {
            $this->rethrowAsValidation($request, $request->rules(...), $conflict);
        }

        return new ProjectResource($project);
    }

    public function update(UpdateProjectRequest $request, Project $project, UpdateProject $updateProject, #[CurrentUser] User $admin): ProjectResource
    {
        return new ProjectResource($updateProject($admin, $project, $request->validated()));
    }
}
