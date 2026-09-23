<?php

namespace App\Http\Controllers\Web\Admin;

use App\Actions\Registry\CreateProject;
use App\Actions\Registry\UpdateProject;
use App\Http\Concerns\RethrowsUniqueConflictAsValidation;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Admin\StoreProjectRequest;
use App\Http\Requests\Web\Admin\UpdateProjectRequest;
use App\Models\KeyType;
use App\Models\Project;
use App\Models\ProjectKeyType;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Contracts\View\View;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;

class ProjectController extends Controller
{
    use RethrowsUniqueConflictAsValidation;

    public function index(): View
    {
        return view('admin.projects.index', [
            'projects' => Project::query()->with(['keyTypes' => fn ($keyTypes) => $keyTypes->orderBy('code')])->orderBy('key')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.projects.create');
    }

    public function store(StoreProjectRequest $request, CreateProject $createProject, #[CurrentUser] User $admin): RedirectResponse
    {
        try {
            $project = $createProject($admin, $request->validated());
        } catch (UniqueConstraintViolationException $conflict) {
            $this->rethrowAsValidation($request, $request->rules(...), $conflict);
        }

        return to_route('admin.projects.show', $project)->with('status', "Проект «{$project->key}» заведён.");
    }

    public function show(Project $project): View
    {
        $pairs = ProjectKeyType::query()->whereBelongsTo($project)->orderByTypeCode()->with('keyType')->get();

        return view('admin.projects.show', [
            'project' => $project,
            // FR-006: every active type, so the set the form sends is the whole set the administrator sees.
            'keyTypes' => KeyType::query()->active()->orderBy('code')->get(),
            'pairs' => $pairs->keyBy('key_type_id'),
            // Edge Cases: enabled before their type was retired; the form cannot re-enable them, so saving drops them.
            'retiredPairs' => $pairs->filter(fn (ProjectKeyType $pair): bool => $pair->is_enabled && ! $pair->keyType->is_active)->values(),
        ]);
    }

    public function update(UpdateProjectRequest $request, Project $project, UpdateProject $updateProject, #[CurrentUser] User $admin): RedirectResponse
    {
        $updateProject($admin, $project, $request->validated());

        return to_route('admin.projects.show', $project)->with('status', "Проект «{$project->key}» сохранён.");
    }
}
