<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Contracts\View\View;

/**
 * Read-only: the registry is changed through the admin REST API, the one place its rules are enforced.
 */
class ProjectController extends Controller
{
    public function index(): View
    {
        return view('admin.projects.index', [
            'projects' => Project::query()->with(['keyTypes' => fn ($keyTypes) => $keyTypes->orderBy('code')])->orderBy('key')->get(),
        ]);
    }
}
