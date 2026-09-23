<?php

namespace App\Domain\Sequence\Exceptions;

use DomainException;

/**
 * Not a DomainRejection: withdrawal exists on the web console only, so it has no REST or MCP error code
 * (specs/003-delete-last-identifier/research.md R5).
 */
final class NotTheLastIdentifier extends DomainException
{
    private function __construct(string $message, public readonly string $lastFormattedId)
    {
        parent::__construct($message);
    }

    public static function tail(string $lastFormattedId): self
    {
        return new self("Удалить можно только последний номер пары: сейчас это {$lastFormattedId}.", $lastFormattedId);
    }
}
