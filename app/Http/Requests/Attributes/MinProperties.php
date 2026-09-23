<?php

namespace App\Http\Requests\Attributes;

use Attribute;

/**
 * The body schema's minProperties in contracts/rest-api.yaml; ClosedBodyRequest enforces it.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class MinProperties
{
    public function __construct(public int $count) {}
}
