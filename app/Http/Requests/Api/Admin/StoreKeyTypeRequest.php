<?php

namespace App\Http\Requests\Api\Admin;

use App\Http\Requests\Api\ClosedBodyRequest;
use App\Models\KeyType;
use App\Rules\FormatTemplate;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class StoreKeyTypeRequest extends ClosedBodyRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', KeyType::class) === true;
    }

    /**
     * @return array<string, list<ValidationRule|object|string>>
     */
    public function rules(): array
    {
        return [
            // The column's case-insensitive collation makes the check refuse `adr` next to `ADR`, as lookups would conflate them.
            'code' => ['required', 'string', 'max:32', Rule::unique(KeyType::class, 'code')],
            'name' => ['required', 'string', 'max:255'],
            'format_template' => ['bail', 'required', 'string', 'max:255', new FormatTemplate],
            'description' => ['nullable', 'string'],
        ];
    }
}
