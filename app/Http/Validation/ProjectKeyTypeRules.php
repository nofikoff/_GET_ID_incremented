<?php

namespace App\Http\Validation;

use App\Rules\EnableableKeyType;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Shared by the API and web requests, as ProjectRules. The web form is reduced to the API's list before these run.
 */
final class ProjectKeyTypeRules
{
    /**
     * Shape, existence and retirement only. The seed is checked against the issued numbers by
     * EnabledKeyTypes under the counter row lock, where an issuance cannot slip in between.
     *
     * @return array<string, list<ValidationRule|string>>
     */
    public static function set(): array
    {
        return [
            // Present but possibly empty: an empty set disables every type of the project.
            'types' => ['present', 'list'],
            'types.*' => ['array'],
            'types.*.code' => ['bail', 'required', 'string', 'max:32', 'distinct:ignore_case', new EnableableKeyType],
            'types.*.seed_sequence' => ['sometimes', 'integer', 'min:0', 'max:4294967295'],
        ];
    }
}
