<?php

namespace App\Http\Requests\Web\Admin;

use App\Http\Validation\ProjectKeyTypeRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;

/**
 * The form sends types[<code>][enabled|seed_sequence] for every type it lists. The API's rules run on the list of the
 * checked ones, and every error keyed by a position in that list is moved under the form field of its type
 * (specs/002-admin-web-console/research.md R3).
 */
class SetProjectKeyTypesRequest extends FormRequest
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
     * @return array<string, mixed>
     */
    public function validationData(): array
    {
        $form = $this->input('types', []);

        // Not a map of types at all: handed to the rules as is, so `list` refuses it rather than it disabling every type.
        return ['types' => is_array($form) ? $this->checked($form) : $form];
    }

    /**
     * The set in the shape the API request's types() gives it to EnabledKeyTypes::replace().
     *
     * @return list<array{code: string, seed_sequence: int|null}>
     */
    public function types(): array
    {
        return array_map(fn (array $type): array => [
            'code' => (string) $type['code'],
            'seed_sequence' => isset($type['seed_sequence']) ? (int) $type['seed_sequence'] : null,
        ], array_values($this->validated('types')));
    }

    /**
     * The form field an error key belongs under: types.<position>.code is the checkbox of the type at that position of
     * types(), types.<position>.seed_sequence its seed field. Any other key is already a form field.
     */
    public function formField(string $key): string
    {
        if (preg_match('/^types\.(\d+)\.(code|seed_sequence)$/', $key, $match) !== 1) {
            return $key;
        }

        $form = $this->input('types', []);
        $code = is_array($form) ? ($this->checked($form)[(int) $match[1]]['code'] ?? null) : null;

        return $code === null ? $key : "types.{$code}.".($match[2] === 'code' ? 'enabled' : 'seed_sequence');
    }

    /**
     * Bug fix: checked() drops an unchecked type's whole entry, seed included, before rules() ever sees it — an
     * admin who typed a seed but forgot the box got a silent no-op instead of a refusal. Runs against the raw
     * form, since validationData() has already stripped the unchecked entries by the time rules() run.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $form = $this->input('types', []);
            if (! is_array($form)) {
                return;
            }

            foreach ($form as $code => $type) {
                if (! is_array($type) || filter_var($type['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                    continue;
                }

                $seed = $type['seed_sequence'] ?? null;
                if ($seed !== null && $seed !== '') {
                    $validator->errors()->add("types.{$code}.seed_sequence", 'Отметьте тип, чтобы задать начальный номер.');
                }
            }
        });
    }

    protected function failedValidation(ValidatorContract $validator): never
    {
        $messages = collect($validator->errors()->messages())
            ->mapWithKeys(fn (array $messages, string $key): array => [$this->formField($key) => $messages]);

        throw ValidationException::withMessages($messages->all())
            ->errorBag($this->errorBag)
            ->redirectTo($this->getRedirectUrl());
    }

    /**
     * A blank seed is left out of its entry, as the API client leaves it out, so it keeps the pair's current seed.
     *
     * @param  array<array-key, mixed>  $form
     * @return list<array{code: string, seed_sequence?: mixed}>
     */
    private function checked(array $form): array
    {
        $checked = [];

        foreach ($form as $code => $type) {
            if (! is_array($type) || ! filter_var($type['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                continue;
            }

            $checked[] = ['code' => (string) $code, ...(isset($type['seed_sequence']) ? ['seed_sequence' => $type['seed_sequence']] : [])];
        }

        return $checked;
    }
}
