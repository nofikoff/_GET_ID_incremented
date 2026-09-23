<?php

namespace App\Http\Controllers\Api;

use App\Domain\Project\ProjectResolver;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ResolveProjectRequest;
use App\Http\Resources\ProjectResolutionResource;

class ProjectResolveController extends Controller
{
    public function __invoke(ResolveProjectRequest $request, ProjectResolver $resolver): ProjectResolutionResource
    {
        return new ProjectResolutionResource($resolver->resolve($request->string('origin')->value()));
    }
}
