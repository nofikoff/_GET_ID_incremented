<?php

namespace App\Http\Resources;

use App\Models\KeyType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * contracts/rest-api.yaml KeyType: unwrapped on its own, under `data` as a collection.
 *
 * @property KeyType $resource
 */
class KeyTypeResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'code' => $this->resource->code,
            'name' => $this->resource->name,
            'format_template' => $this->resource->format_template,
            'description' => $this->resource->description,
            'is_active' => $this->resource->is_active,
        ];
    }
}
