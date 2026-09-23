<?php

namespace App\Actions;

use DomainException;

/**
 * A Google identity the service does not admit. The message is shown on the sign-in page as is.
 */
final class SignInRefused extends DomainException
{
    public static function outsideDomain(string $domain): self
    {
        return new self("Вход открыт только подтверждённым адресам @{$domain}.");
    }

    public static function deactivated(): self
    {
        return new self('Эта учётная запись выведена из обращения. Если это ошибка, обратитесь к администратору get-id.');
    }
}
