<?php

namespace App\Rules;

use App\Domain\KeyType\IdentifierFormat;
use App\Domain\KeyType\InvalidFormatTemplate;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * FR-013a: a template is refused when saved, with IdentifierFormat's own reason.
 */
class FormatTemplate implements ValidationRule
{
    /**
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        try {
            IdentifierFormat::parse((string) $value);
        } catch (InvalidFormatTemplate $invalid) {
            $fail($invalid->getMessage());
        }
    }
}
