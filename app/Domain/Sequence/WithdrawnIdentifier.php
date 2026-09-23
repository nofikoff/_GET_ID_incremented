<?php

namespace App\Domain\Sequence;

final readonly class WithdrawnIdentifier
{
    public function __construct(
        public string $type,
        public string $name,
        public int $sequenceNumber,
        // The counter as it stood, not the number taken off: they differ once the counter drifted (FR-004).
        public int $previousLastSequence,
        public int $lastSequence,
        public int $nextSequence,
    ) {}
}
