<?php

namespace App\Domain\Sequence\Exceptions;

final class EmptyDocumentName extends DomainRejection
{
    public static function from(string $name): self
    {
        return new self(sprintf('Тема документа «%s» пуста после нормализации: в ней нет ни букв, ни цифр.', $name));
    }

    public function errorCode(): string
    {
        return 'name_empty_after_normalization';
    }
}
