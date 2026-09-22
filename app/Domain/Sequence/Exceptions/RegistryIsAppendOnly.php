<?php

namespace App\Domain\Sequence\Exceptions;

use LogicException;

/**
 * A programming error, not a client rejection: no code path may rewrite or remove an issued number.
 */
final class RegistryIsAppendOnly extends LogicException
{
    public static function attempted(string $operation): self
    {
        return new self("The identifiers registry is append-only; {$operation} is not allowed.");
    }
}
