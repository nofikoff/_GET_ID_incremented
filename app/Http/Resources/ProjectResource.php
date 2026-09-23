<?php

namespace App\Http\Resources;

use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * contracts/rest-api.yaml Project: unwrapped on its own, under `data` as a collection.
 *
 * @property Project $resource
 */
class ProjectResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'key' => $this->resource->key,
            'name' => $this->resource->name,
            'repo_url' => $this->resource->repo_url,
            'description' => $this->resource->description,
            'is_active' => $this->resource->is_active,
            'created_at' => $this->resource->created_at?->toIso8601ZuluString(),
        ];
    }
}
