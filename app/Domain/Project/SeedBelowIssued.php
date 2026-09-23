<?php

namespace App\Domain\Project;

use DomainException;

/**
 * FR-014b. Carries the position of the offending entry in the set it came with, so the transport can point at it.
 */
final class SeedBelowIssued extends DomainException
{
    private function __construct(string $message, public readonly int $position)
    {
        parent::__construct($message);
    }

    public static function at(int $position, string $type, int $seed, int $lastIssued): self
    {
        return new self("Начальный номер {$seed} ниже уже выданного номера {$lastIssued} по типу «{$type}» в этом проекте.", $position);
    }
}
