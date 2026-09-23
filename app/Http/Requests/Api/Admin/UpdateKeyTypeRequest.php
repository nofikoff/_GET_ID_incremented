<?php

namespace App\Http\Requests\Api\Admin;

use App\Rules\FormatTemplate;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The code is not editable: clients and the registry refer to the type by it.
 */
class UpdateKeyTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('keyType')) === true;
    }

    /**
     * @return array<string, list<ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'format_template' => ['sometimes', 'bail', 'required', 'string', 'max:255', new FormatTemplate],
            'description' => ['sometimes', 'nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
