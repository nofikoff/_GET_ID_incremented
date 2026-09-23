<?php

namespace App\Domain\Sequence\Exceptions;

use DomainException;

/**
 * Not a DomainRejection: withdrawal exists on the web console only, so it has no REST or MCP error code
 * (specs/003-delete-last-identifier/research.md R5).
 */
final class NotTheLastIdentifier extends DomainException
{
    private function __construct(string $message, public readonly string $type, public readonly int $lastSequenceNumber)
    {
        parent::__construct($message);
    }

    /**
     * Names the number as "code number" (ADR 34): the document name is the consumer's (specs/004-index-only-numbers, R4).
     */
    public static function tail(string $code, int $number): self
    {
        return new self("Удалить можно только последний номер пары: сейчас это {$code} {$number}.", $code, $number);
    }
}
