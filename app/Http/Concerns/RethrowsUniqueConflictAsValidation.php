<?php

namespace App\Http\Concerns;

use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * A uniqueness rule checks with a SELECT at validation time; two requests for the same value can both
 * pass, and the loser's INSERT then hits the index. Re-running the request's own rules against the
 * now-current row reproduces the 422 the rule would have given on the first pass, word for word, so
 * the race is indistinguishable on the wire from an ordinary duplicate.
 */
trait RethrowsUniqueConflictAsValidation
{
    /**
     * @param  Closure  $rules  $request->rules(...): a first-class callable, so PHPStan resolves it on
     *                          the concrete FormRequest (rules() is not on the base class) and the container can still inject
     *                          whatever the method declares, e.g. #[CurrentUser]
     *
     * @throws ValidationException always: the row this request lost the race to now exists, so the same
     *                             rules that passed a moment ago fail when run again
     */
    private function rethrowAsValidation(FormRequest $request, Closure $rules, UniqueConstraintViolationException $conflict): never
    {
        /** @var array<string, mixed> $resolvedRules */
        $resolvedRules = app()->call($rules);

        Validator::make($request->validationData(), $resolvedRules)->validate();

        throw $conflict;
    }
}
