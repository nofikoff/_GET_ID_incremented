<?php

namespace App\Console\Commands;

use App\Actions\DeactivateUser;
use App\Actions\LastAdministrator;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Part of the leaving procedure (FR-021b): an issued token never asks Google, so a deleted Google account alone
 * does not stop it.
 */
#[Signature('user:deactivate
    {email : The address the person signs in with}')]
#[Description('Retire an employee and revoke all their tokens')]
final class DeactivateUserCommand extends Command
{
    public function handle(DeactivateUser $deactivate): int
    {
        // Sign-in stores addresses lower-cased.
        $email = Str::lower((string) $this->argument('email'));
        $user = User::query()->where('email', $email)->first();
        if ($user === null) {
            $this->error("No account signs in as {$email}.");

            return self::FAILURE;
        }

        try {
            $deactivate($user);
        } catch (LastAdministrator $refused) {
            $this->error($refused->getMessage());

            return self::FAILURE;
        }

        $this->info("{$user->email} is deactivated; their tokens are revoked.");

        return self::SUCCESS;
    }
}
