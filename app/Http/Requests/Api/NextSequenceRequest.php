<?php

namespace App\Http\Requests\Api;

/**
 * Shape only. A theme that normalizes to nothing is left to DocumentName, so the client gets the
 * contract's name_empty_after_normalization code instead of a generic validation error.
 */
class NextSequenceRequest extends ClosedBodyRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'project_key' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'max:32'],
            'name' => ['required', 'string', 'max:255'],
        ];
    }
}
