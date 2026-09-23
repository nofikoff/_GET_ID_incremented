<?php

namespace App\Actions;

use App\Actions\Concerns\KeepsAnAdministrator;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * FR-021b: the person's tokens go, the account stays, so the numbers they issued keep their author.
 * An open browser session dies with it too: the web guard only retrieves active users (AppServiceProvider).
 */
final class DeactivateUser
{
    use KeepsAnAdministrator;

    /**
     * @throws LastAdministrator
     */
    public function __invoke(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $this->ensureAnotherAdministrator($user);

            $user->deactivated_at ??= now();
            $user->save();
            $user->tokens()->delete();
        });
    }
}
