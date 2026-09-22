<?php

namespace App\Domain\KeyType;

use App\Domain\Sequence\Exceptions\EmptyDocumentName;
use Normalizer;

/**
 * A document theme as the client spelled it, plus the slug the registry compares by (FR-007).
 */
final readonly class DocumentName
{
    private function __construct(
        public string $original,
        public string $slug,
    ) {}

    /**
     * @throws EmptyDocumentName
     */
    public static function fromString(string $name): self
    {
        $slug = self::slug($name);

        if ($slug === '') {
            throw EmptyDocumentName::from($name);
        }

        return new self($name, $slug);
    }

    private static function slug(string $name): string
    {
        // Lower-casing can decompose a letter (İ becomes i plus a combining dot), hence NFC on both sides.
        $lower = Normalizer::normalize(mb_strtolower(Normalizer::normalize($name) ?: '', 'UTF-8')) ?: '';

        // Marks (\p{M}) stay: in Devanagari and similar scripts the vowel signs are part of the letter.
        return trim(preg_replace('/[^\p{L}\p{M}\p{N}]+/u', '-', $lower) ?? '', '-');
    }
}
