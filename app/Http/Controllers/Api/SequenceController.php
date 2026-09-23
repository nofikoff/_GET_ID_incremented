<?php

namespace App\Http\Controllers\Api;

use App\Domain\Sequence\SequenceIssuer;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ListSequenceRequest;
use App\Http\Requests\Api\NextSequenceRequest;
use App\Http\Resources\IdentifierListResource;
use App\Http\Resources\IssuedIdentifierResource;

class SequenceController extends Controller
{
    public function __construct(private readonly SequenceIssuer $issuer) {}

    public function next(NextSequenceRequest $request): IssuedIdentifierResource
    {
        return new IssuedIdentifierResource($this->issuer->issue(
            $request->string('project_key')->value(),
            $request->string('type')->value(),
            $request->string('name')->value(),
            $request->user(),
        ));
    }

    public function list(ListSequenceRequest $request): IdentifierListResource
    {
        return new IdentifierListResource($this->issuer->list(
            $request->string('project_key')->value(),
            $request->string('type')->value(),
        ));
    }
}
