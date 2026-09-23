<?php

namespace App\Http\Requests\Api\Admin;

use App\Http\Requests\Api\ClosedBodyRequest;
use App\Http\Validation\ProjectKeyTypeRules;
use Illuminate\Contracts\Validation\ValidationRule;

class SetProjectKeyTypesRequest extends ClosedBodyRequest
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
        return ProjectKeyTypeRules::set();
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
