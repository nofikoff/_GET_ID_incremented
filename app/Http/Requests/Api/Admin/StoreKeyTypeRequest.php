<?php

namespace App\Http\Requests\Api\Admin;

use App\Http\Requests\Api\ClosedBodyRequest;
use App\Http\Validation\KeyTypeRules;
use App\Models\KeyType;
use Illuminate\Contracts\Validation\ValidationRule;

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
        return KeyTypeRules::store();
    }
}
