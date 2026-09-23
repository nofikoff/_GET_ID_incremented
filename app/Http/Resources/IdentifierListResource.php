<?php

namespace App\Http\Resources;

use App\Domain\Sequence\IdentifierList;
use App\Domain\Sequence\IssuedIdentifier;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The listSequence response of contracts/rest-api.yaml, unwrapped.
 *
 * @property IdentifierList $resource
 */
class IdentifierListResource extends JsonResource
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
            'items' => array_map(fn (IssuedIdentifier $issued): array => [
                'sequence_number' => $issued->sequenceNumber,
                'name' => $issued->name,
                'formatted_id' => $issued->formattedId,
                'created_at' => $issued->issuedAt->toIso8601ZuluString(),
            ], $this->resource->items),
        ];
    }
}
