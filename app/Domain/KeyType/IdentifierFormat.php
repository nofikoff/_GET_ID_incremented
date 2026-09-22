<?php

namespace App\Domain\KeyType;

use InvalidArgumentException;

/**
 * A key type's format template (FR-013a). The placeholder grammar is closed — `{number}`,
 * `{number:0Nd}`, `{name}` — and is substituted by hand rather than through sprintf, which would
 * hand control of the output to whatever `%` sequences an administrator typed (research.md R7).
 */
final readonly class IdentifierFormat
{
    private const NUMBER = '\{number(?::0(?<width>[1-9][0-9]?)d)?\}';

    private const PLACEHOLDER = '/'.self::NUMBER.'|\{(?<name>name)\}/';

    private function __construct(public string $template) {}

    /**
     * @throws InvalidFormatTemplate
     */
    public static function parse(string $template): self
    {
        $literals = preg_replace(self::PLACEHOLDER, '', $template) ?? '';

        if (preg_match('/\{[^{}]*\}/', $literals, $unknown) === 1) {
            throw InvalidFormatTemplate::unknownPlaceholder($template, $unknown[0]);
        }
        if (strpbrk($literals, '{}') !== false) {
            throw InvalidFormatTemplate::strayBrace($template);
        }
        if (preg_match('/'.self::NUMBER.'/', $template) !== 1) {
            throw InvalidFormatTemplate::missingNumber($template);
        }

        return new self($template);
    }

    /**
     * The width is a minimum: a longer number is printed in full, never truncated.
     */
    public function format(int $number, DocumentName $name): string
    {
        if ($number < 1) {
            throw new InvalidArgumentException("Issued numbers start at 1, got {$number}.");
        }

        return preg_replace_callback(
            self::PLACEHOLDER,
            static fn (array $match): string => $match['name'] !== null
                ? $name->slug
                : str_pad((string) $number, (int) ($match['width'] ?? 0), '0', STR_PAD_LEFT),
            $this->template,
            flags: PREG_UNMATCHED_AS_NULL,
        ) ?? $this->template;
    }
}
