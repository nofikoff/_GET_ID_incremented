<?php

namespace App\Domain\Sequence\Exceptions;

use App\Domain\Project\ProjectKey;

final class UnknownProject extends DomainRejection
{
    public static function forKey(ProjectKey $key): self
    {
        return new self(
            "Проект «{$key}» не зарегистрирован. Попросите администратора завести его, указав этот ключ.",
            ['project_key' => $key->value],
        );
    }

    public function errorCode(): string
    {
        return 'project_not_registered';
    }
}
