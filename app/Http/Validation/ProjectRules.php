<?php

namespace App\Http\Validation;

use App\Rules\UnregisteredOrigin;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * The API and web requests both return these sets, so a rule added here holds on every transport
 * (specs/002-admin-web-console/research.md R1).
 */
final class ProjectRules
{
    /**
     * The key is never an input: it is derived from repo_url (FR-008).
     *
     * @return array<string, list<ValidationRule|string>>
     */
    public static function store(): array
    {
        return [
            'repo_url' => ['bail', 'required', 'string', 'max:2048', new UnregisteredOrigin],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ];
    }

    /**
     * repo_url is not editable: the key derived from it is what clients resolve their origin to.
     *
     * @return array<string, list<string>>
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
