<?php

namespace App\Http\Requests\Api\Admin;

use App\Http\Requests\Api\ClosedBodyRequest;
use App\Http\Requests\Attributes\MinProperties;
use App\Http\Validation\ProjectRules;

#[MinProperties(1)]
class UpdateProjectRequest extends ClosedBodyRequest
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
