<?php

namespace App\Domain\Project;

use DomainException;

/**
 * A type retired globally cannot be enabled anywhere (Edge Cases). Carries the position of the offending entry,
 * like SeedBelowIssued, so the transport can point at it.
 */
final class RetiredKeyType extends DomainException
{
    private function __construct(string $message, public readonly int $position)
    {
        parent::__construct($message);
    }

    public static function at(int $position, string $type): self
    {
        return new self(self::message($type), $position);
    }

    // Shared with EnableableKeyType, so a type retired after validation reads exactly like one retired before it.
    public static function message(string $type): string
    {
        return "Тип «{$type}» выведен из обращения и не может быть включён.";
    }
}
