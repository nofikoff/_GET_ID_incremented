<?php

namespace App\Http\Requests\Api;

use App\Http\Requests\Attributes\MinProperties;
use Closure;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

/**
 * A body whose schema in contracts/rest-api.yaml is additionalProperties: false: a field outside rules() is a
 * validation error rather than silently dropped. Query strings stay open, as the contract does not close them.
 */
#[FailOnUnknownFields]
abstract class ClosedBodyRequest extends FormRequest
{
    /**
     * The declared fields: a key outside these is refused.
     *
     * @return array<string, mixed>
     */
    abstract public function rules(): array;

    /**
     * @return list<Closure(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $minimum = $this->nearestClassWithAttribute([MinProperties::class])
                ?->getAttributes(MinProperties::class)[0]->newInstance()->count ?? 0;

            if (count($this->validationData()) < $minimum) {
                $declared = collect(array_keys($this->rules()))->map(fn (string $key): string => Str::before($key, '.'))->unique();

                $validator->errors()->add('body', "Число полей в теле запроса должно быть не меньше {$minimum}. Допустимые поля: {$declared->implode(', ')}.");
            }
        }];
    }
}
