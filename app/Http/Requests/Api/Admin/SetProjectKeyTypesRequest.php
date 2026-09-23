<?php

namespace App\Http\Requests\Api\Admin;

use App\Rules\EnableableKeyType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Shape, existence and retirement only. The seed is checked against the issued numbers by
 * EnabledKeyTypes under the counter row lock, where an issuance cannot slip in between.
 */
class SetProjectKeyTypesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('project')) === true;
    }

    /**
     * @return array<string, list<ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            // Present but possibly empty: an empty set disables every type of the project.
            'types' => ['present', 'list'],
            'types.*' => ['array'],
            'types.*.code' => ['bail', 'required', 'string', 'max:32', 'distinct:ignore_case', new EnableableKeyType],
            'types.*.seed_sequence' => ['sometimes', 'integer', 'min:0', 'max:4294967295'],
        ];
    }

    /**
     * @return list<array{code: string, seed_sequence: int|null}>
     */
    public function types(): array
    {
        return array_map(fn (array $type): array => [
            'code' => (string) $type['code'],
            'seed_sequence' => isset($type['seed_sequence']) ? (int) $type['seed_sequence'] : null,
        ], array_values($this->validated('types')));
    }
}
