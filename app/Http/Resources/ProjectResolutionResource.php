<?php

namespace App\Http\Resources;

use App\Domain\Project\AvailableKeyType;
use App\Domain\Project\ProjectResolution;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The resolveProject response of contracts/rest-api.yaml, unwrapped.
 *
 * @property ProjectResolution $resource
 */
class ProjectResolutionResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'project_key' => $this->resource->projectKey,
            'registered' => $this->resource->registered,
            'active' => $this->resource->active,
            'name' => $this->resource->name,
            'types' => array_map(fn (AvailableKeyType $type): array => [
                'code' => $type->code,
                'name' => $type->name,
                'next_number' => $type->nextNumber,
            ], $this->resource->types),
            'hint' => $this->resource->hint,
        ];
    }
}
