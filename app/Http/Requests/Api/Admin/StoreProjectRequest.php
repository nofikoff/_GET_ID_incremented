<?php

namespace App\Http\Requests\Api\Admin;

use App\Models\Project;
use App\Rules\UnregisteredOrigin;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The key is never an input: it is derived from repo_url (FR-008).
 */
class StoreProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Project::class) === true;
    }

    /**
     * @return array<string, list<ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'repo_url' => ['bail', 'required', 'string', 'max:2048', new UnregisteredOrigin],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ];
    }
}
