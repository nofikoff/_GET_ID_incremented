<?php

namespace App\Domain\Sequence\Exceptions;

use Illuminate\Database\UniqueConstraintViolationException;
use LogicException;

/**
 * A server defect, not a client rejection (FR-004b): under the counter row lock a number cannot collide,
 * so one that did means serialization broke or the registry was written past the counter.
 */
final class SequenceNumberCollision extends LogicException
{
    private function __construct(
        string $message,
        private readonly string $projectKey,
        private readonly string $type,
        private readonly int $sequenceNumber,
        UniqueConstraintViolationException $previous,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public static function at(string $projectKey, string $type, int $sequenceNumber, UniqueConstraintViolationException $previous): self
    {
        return new self(
            "Number {$sequenceNumber} of {$type} in {$projectKey} is already in the registry, yet the counter offered it.",
            $projectKey,
            $type,
            $sequenceNumber,
            $previous,
        );
    }

    /**
     * Picked up by the exception handler as the log context.
     *
     * @return array{project_key: string, type: string, sequence_number: int}
     */
    public function context(): array
    {
        return ['project_key' => $this->projectKey, 'type' => $this->type, 'sequence_number' => $this->sequenceNumber];
    }
}
