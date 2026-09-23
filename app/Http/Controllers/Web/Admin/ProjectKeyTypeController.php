<?php

namespace App\Http\Controllers\Web\Admin;

use App\Actions\Registry\RegistryChangeLog;
use App\Domain\Project\EnabledKeyTypes;
use App\Domain\Project\RetiredKeyType;
use App\Domain\Project\SeedBelowIssued;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Admin\SetProjectKeyTypesRequest;
use App\Models\Project;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class ProjectKeyTypeController extends Controller
{
    public function __invoke(
        SetProjectKeyTypesRequest $request,
        Project $project,
        EnabledKeyTypes $enabledKeyTypes,
        RegistryChangeLog $changeLog,
        #[CurrentUser] User $admin,
    ): RedirectResponse {
        try {
            $changeLog->keyTypesSet($admin, $project, fn (): Collection => $enabledKeyTypes->replace($project, $request->types()));
        } catch (SeedBelowIssued $refused) {
            throw ValidationException::withMessages([$request->formField("types.{$refused->position}.seed_sequence") => $refused->getMessage()]);
        } catch (RetiredKeyType $refused) {
            throw ValidationException::withMessages([$request->formField("types.{$refused->position}.code") => $refused->getMessage()]);
        }

        return to_route('admin.projects.show', $project)->with('status', 'Набор типов проекта сохранён.');
    }
}
