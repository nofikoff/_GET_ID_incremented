<?php

namespace App\Http\Resources;

use App\Domain\Sequence\IssuedIdentifier;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * contracts/rest-api.yaml IssuedIdentifier, unwrapped.
 *
 * @property IssuedIdentifier $resource
 */
class IssuedIdentifierResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'project_key' => $this->resource->projectKey,
            'type' => $this->resource->type,
            'name' => $this->resource->name,
            'sequence_number' => $this->resource->sequenceNumber,
            'formatted_id' => $this->resource->formattedId,
            'is_new' => $this->resource->isNew,
            'created_at' => $this->resource->issuedAt->toIso8601ZuluString(),
        ];
    }
}
