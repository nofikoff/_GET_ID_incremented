<?php

namespace App\Domain\Sequence\Exceptions;

use App\Domain\Project\ProjectKey;

final class InactiveKeyType extends DomainRejection
{
    public static function inProject(ProjectKey $key, string $type): self
    {
        return new self(
            "Тип «{$type}» выведен из обращения: новые номера по нему не выдаются ни в одном проекте. Вернуть его может администратор.",
            ['project_key' => $key->value, 'type' => $type],
        );
    }

    public function errorCode(): string
    {
        return 'type_inactive';
    }
}
