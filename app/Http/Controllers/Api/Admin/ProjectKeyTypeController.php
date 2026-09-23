<?php

namespace App\Http\Controllers\Api\Admin;

use App\Actions\Registry\RegistryChangeLog;
use App\Domain\Project\EnabledKeyTypes;
use App\Domain\Project\RetiredKeyType;
use App\Domain\Project\SeedBelowIssued;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\SetProjectKeyTypesRequest;
use App\Http\Resources\EnabledKeyTypeResource;
use App\Models\Project;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

class ProjectKeyTypeController extends Controller
{
    public function __invoke(
        SetProjectKeyTypesRequest $request,
        Project $project,
        EnabledKeyTypes $enabledKeyTypes,
        RegistryChangeLog $changeLog,
        #[CurrentUser] User $admin,
    ): AnonymousResourceCollection {
        try {
            $pairs = $changeLog->keyTypesSet(
                $admin,
                $project,
                fn (): Collection => $enabledKeyTypes->replace($project, $request->types()),
            );
        } catch (SeedBelowIssued $refused) {
            throw ValidationException::withMessages(["types.{$refused->position}.seed_sequence" => $refused->getMessage()]);
        } catch (RetiredKeyType $refused) {
            throw ValidationException::withMessages(["types.{$refused->position}.code" => $refused->getMessage()]);
        }

        return EnabledKeyTypeResource::collection($pairs);
    }
}
