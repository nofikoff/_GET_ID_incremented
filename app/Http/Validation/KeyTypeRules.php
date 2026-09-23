<?php

namespace App\Http\Validation;

use App\Models\KeyType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

/**
 * Shared by the API and web requests, as ProjectRules.
 */
final class KeyTypeRules
{
    /**
     * @return array<string, list<ValidationRule|object|string>>
     */
    public static function store(): array
    {
        return [
            // The column's case-insensitive collation makes the check refuse `adr` next to `ADR`, as lookups would conflate them.
            'code' => ['required', 'string', 'max:32', Rule::unique(KeyType::class, 'code')],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ];
    }

    /**
     * The code is not editable: clients and the registry refer to the type by it.
     *
     * @return array<string, list<ValidationRule|string>>
     */
    public static function update(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
