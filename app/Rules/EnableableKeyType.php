<?php

namespace App\Rules;

use App\Models\KeyType;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * A key type code that exists and is not retired: a type retired globally cannot be enabled anywhere (Edge Cases).
 */
class EnableableKeyType implements ValidationRule
{
    /**
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $code = (string) $value;
        $keyType = KeyType::query()->where('code', $code)->first();

        if ($keyType === null) {
            $fail("Тип «{$code}» не заведён.");
        } elseif (! $keyType->is_active) {
            $fail("Тип «{$keyType->code}» выведен из обращения и не может быть включён.");
        }
    }
}
