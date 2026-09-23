<?php

namespace App\Http\Requests\Web\Admin;

use App\Http\Validation\KeyTypeRules;
use App\Models\KeyType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreKeyTypeRequest extends FormRequest
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
