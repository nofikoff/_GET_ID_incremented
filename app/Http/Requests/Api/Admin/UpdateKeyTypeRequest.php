<?php

namespace App\Http\Requests\Api\Admin;

use App\Http\Requests\Api\ClosedBodyRequest;
use App\Http\Requests\Attributes\MinProperties;
use App\Http\Validation\KeyTypeRules;
use Illuminate\Contracts\Validation\ValidationRule;

#[MinProperties(1)]
class UpdateKeyTypeRequest extends ClosedBodyRequest
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
        return KeyTypeRules::update();
    }
}
