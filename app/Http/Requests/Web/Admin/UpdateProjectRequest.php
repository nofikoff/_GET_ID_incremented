<?php

namespace App\Http\Requests\Web\Admin;

use App\Http\Validation\ProjectRules;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('project')) === true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return ProjectRules::update();
    }
}
