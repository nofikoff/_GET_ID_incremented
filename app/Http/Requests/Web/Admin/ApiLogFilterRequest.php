<?php

namespace App\Http\Requests\Web\Admin;

use App\Models\User;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * FR-014, FR-015: the journal's GET filter. No REST twin exists for this screen, so unlike the other web requests
 * (specs/002-admin-web-console/research.md R1) its rules live here rather than in a shared *Rules class.
 */
class ApiLogFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('administer') === true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['nullable', 'integer', Rule::exists(User::class, 'id')],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'surface' => ['nullable', Rule::in(['rest', 'mcp'])],
        ];
    }

    public function withValidator(ValidatorContract $validator): void
    {
        $validator->after(function (ValidatorContract $validator): void {
            $from = $this->input('from');
            $to = $this->input('to');

            if ($from !== null && $to !== null && Carbon::parse($to)->lt(Carbon::parse($from))) {
                $validator->errors()->add('to', 'Дата «по» не может быть раньше даты «с».');
            }
        });
    }
}
