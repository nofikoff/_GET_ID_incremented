<?php

namespace App\Http\Requests\Web;

use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * The name tells one person's tokens apart (one per machine) and is what the journal records, so it is unique per owner.
 */
class StoreTokenRequest extends FormRequest
{
    /**
     * @return array<string, list<object|string>>
     */
    public function rules(#[CurrentUser] User $owner): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique(PersonalAccessToken::class, 'name')->where(fn (Builder $tokens): Builder => $tokens
                    ->where('tokenable_type', $owner->getMorphClass())
                    ->where('tokenable_id', $owner->getKey())),
            ],
        ];
    }
}
