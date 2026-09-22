<?php

namespace App\Domain\Sequence\Exceptions;

use App\Domain\Project\ProjectKey;

final class InactiveProject extends DomainRejection
{
    public static function forKey(ProjectKey $key): self
    {
        return new self(
            "Проект «{$key}» выведен из обращения: новые номера по нему не выдаются. Вернуть его может администратор.",
            ['project_key' => $key->value],
        );
    }

    public function errorCode(): string
    {
        return 'project_inactive';
    }
}
