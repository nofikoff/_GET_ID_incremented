<?php

namespace App\Actions\Concerns;

use App\Actions\LastAdministrator;
use App\Enums\UserRole;
use App\Models\User;

trait KeepsAnAdministrator
{
    /**
     * Call inside the transaction that takes $user's administration away. Every active administrator row is
     * locked, not only the others: two concurrent demotions would otherwise each count the other as the one who stays.
     *
     * @throws LastAdministrator
     */
    private function ensureAnotherAdministrator(User $user): void
    {
        $administrators = User::query()->active()->where('role', UserRole::Admin)->lockForUpdate()->pluck('id');

        if ($administrators->count() === 1 && $administrators->contains($user->getKey())) {
            throw LastAdministrator::wouldLeave($user);
        }
    }
}
