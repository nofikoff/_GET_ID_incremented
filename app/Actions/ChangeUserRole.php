<?php

namespace App\Actions;

use App\Actions\Concerns\KeepsAnAdministrator;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class ChangeUserRole
{
    use KeepsAnAdministrator;

    /**
     * @throws LastAdministrator
     */
    public function __invoke(User $user, UserRole $role): void
    {
        DB::transaction(function () use ($user, $role): void {
            if ($role !== UserRole::Admin) {
                $this->ensureAnotherAdministrator($user);
            }

            $user->role = $role;
            $user->save();
        });
    }
}
