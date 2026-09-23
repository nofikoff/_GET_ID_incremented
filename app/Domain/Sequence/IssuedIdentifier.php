<?php

namespace App\Domain\Sequence;

use Carbon\CarbonImmutable;

/**
 * An issued number as both transports return it. `name` is the wording of the first issuance, never the
 * current request's (FR-007), and `isNew` is false for a repeat and for every listed entry. The document
 * name is the consumer's to build (specs/004-index-only-numbers).
 */
final readonly class IssuedIdentifier
{
    public function __construct(
        public string $projectKey,
        public string $type,
        public string $name,
        public int $sequenceNumber,
        public bool $isNew,
        public CarbonImmutable $issuedAt,
    ) {}
}
