<?php

namespace App\Domain\Sequence\Exceptions;

final class UnparsableOrigin extends DomainRejection
{
    public static function because(string $origin, string $reason): self
    {
        return new self(sprintf('Адрес репозитория «%s» не разобран: %s.', trim($origin), $reason));
    }

    public function errorCode(): string
    {
        return 'origin_unparsable';
    }
}
