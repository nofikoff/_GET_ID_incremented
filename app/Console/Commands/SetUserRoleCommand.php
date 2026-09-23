<?php

namespace App\Console\Commands;

use App\Actions\ChangeUserRole;
use App\Actions\LastAdministrator;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * FR-021: roles change only here, on the server; the web interface has no user management.
 */
#[Signature('user:role
    {email : The address the person signs in with}
    {role : admin or member}')]
#[Description('Appoint or demote an administrator')]
final class SetUserRoleCommand extends Command
{
    public function handle(ChangeUserRole $changeRole): int
    {
        $role = UserRole::tryFrom((string) $this->argument('role'));
        if ($role === null) {
            $this->error('The role is either admin or member.');

            return self::INVALID;
        }

        // Sign-in stores addresses lower-cased.
        $email = Str::lower((string) $this->argument('email'));
        $user = User::query()->where('email', $email)->first();
        if ($user === null) {
            $this->error("No account signs in as {$email}.");

            return self::FAILURE;
        }

        try {
            $changeRole($user, $role);
        } catch (LastAdministrator $refused) {
            $this->error($refused->getMessage());

            return self::FAILURE;
        }

        $this->info("{$user->email} is now {$role->value}.");

        return self::SUCCESS;
    }
}
