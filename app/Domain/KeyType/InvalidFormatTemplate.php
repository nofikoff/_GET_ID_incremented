<?php

namespace App\Domain\KeyType;

use InvalidArgumentException;

final class InvalidFormatTemplate extends InvalidArgumentException
{
    public static function missingNumber(string $template): self
    {
        return new self("Шаблон «{$template}» не содержит номера: нужен {number} или {number:0Nd}.");
    }

    public static function unknownPlaceholder(string $template, string $placeholder): self
    {
        return new self("Шаблон «{$template}» содержит неизвестный плейсхолдер {$placeholder}: допустимы {number}, {number:0Nd} и {name}.");
    }

    public static function strayBrace(string $template): self
    {
        return new self("Шаблон «{$template}» содержит непарную фигурную скобку.");
    }
}
