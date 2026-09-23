<?php

namespace App\Rules;

use App\Domain\Project\ProjectKey;
use App\Domain\Sequence\Exceptions\UnparsableOrigin;
use App\Models\Project;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * A repository address that parses into a project key no project holds yet. Uniqueness is checked on
 * the derived key, so the SSH and HTTPS forms of one repository cannot register twice.
 */
class UnregisteredOrigin implements ValidationRule
{
    /**
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        try {
            $key = ProjectKey::fromOrigin((string) $value);
        } catch (UnparsableOrigin $unparsable) {
            $fail($unparsable->getMessage());

            return;
        }

        if (Project::query()->where('key', $key->value)->exists()) {
            $fail("Проект с ключом «{$key}» уже зарегистрирован.");
        }
    }
}
