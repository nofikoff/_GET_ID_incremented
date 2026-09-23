<?php

namespace App\Http\Controllers\Api\Admin;

use App\Domain\Project\ProjectKey;
use App\Http\Concerns\RethrowsUniqueConflictAsValidation;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\StoreProjectRequest;
use App\Http\Requests\Api\Admin\UpdateProjectRequest;
use App\Http\Resources\ProjectResource;
use App\Models\Project;
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

    public function store(StoreProjectRequest $request): ProjectResource
    {
        $project = new Project($request->safe()->only(['repo_url', 'name', 'description']));
        $project->key = ProjectKey::fromOrigin($project->repo_url)->value;
        $project->creator()->associate($request->user());

        try {
            $project->save();
        } catch (UniqueConstraintViolationException $conflict) {
            $this->rethrowAsValidation($request, $request->rules(...), $conflict);
        }

        return new ProjectResource($project);
    }

    public function update(UpdateProjectRequest $request, Project $project): ProjectResource
    {
        $project->update($request->validated());

        return new ProjectResource($project);
    }
}
