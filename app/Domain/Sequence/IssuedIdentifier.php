<?php

namespace App\Domain\Sequence;

use Carbon\CarbonImmutable;

/**
 * An issued number as both transports return it. `name` is the wording of the first issuance, never the
 * current request's (FR-007), and `isNew` is false for a repeat and for every listed entry.
 */
final readonly class IssuedIdentifier
{
    public function __construct(
        public string $projectKey,
        public string $type,
        public string $name,
        public int $sequenceNumber,
        public string $formattedId,
        public bool $isNew,
        public CarbonImmutable $issuedAt,
    ) {}
}
