<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class ListSequenceRequest extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'project_key' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'max:32'],
        ];
    }
}
