<?php

namespace App\Domain\Sequence\Exceptions;

use App\Domain\Project\ProjectKey;

final class TypeNotEnabled extends DomainRejection
{
    public static function inProject(ProjectKey $key, string $type): self
    {
        return new self(
            "Тип «{$type}» не включён в проекте «{$key}». Попросите администратора включить его.",
            ['project_key' => $key->value, 'type' => $type],
        );
    }

    public function errorCode(): string
    {
        return 'type_not_enabled';
    }
}
