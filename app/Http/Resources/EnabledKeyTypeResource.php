<?php

namespace App\Http\Resources;

use App\Models\ProjectKeyType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * contracts/rest-api.yaml EnabledKeyType: one project and key type pair with its counter.
 *
 * @property ProjectKeyType $resource
 */
class EnabledKeyTypeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'code' => $this->resource->keyType->code,
            'name' => $this->resource->keyType->name,
            'seed_sequence' => $this->resource->seed_sequence,
            'last_sequence' => $this->resource->last_sequence,
            'next_number' => $this->resource->nextSequence(),
        ];
    }
}
