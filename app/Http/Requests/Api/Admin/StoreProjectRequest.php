<?php

namespace App\Http\Requests\Api\Admin;

use App\Http\Requests\Api\ClosedBodyRequest;
use App\Http\Validation\ProjectRules;
use App\Models\Project;
use Illuminate\Contracts\Validation\ValidationRule;

class StoreProjectRequest extends ClosedBodyRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Project::class) === true;
    }

    /**
     * @return array<string, list<ValidationRule|string>>
     */
    public function rules(): array
    {
        return ProjectRules::store();
    }
}
