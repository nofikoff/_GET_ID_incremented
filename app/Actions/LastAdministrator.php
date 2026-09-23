<?php

namespace App\Actions;

use App\Models\User;
use DomainException;

/**
 * FR-021a: without an active administrator the registry can only be managed by editing the database.
 */
final class LastAdministrator extends DomainException
{
    public static function wouldLeave(User $user): self
    {
        return new self("{$user->email} is the last active administrator; appoint another one first (user:role <email> admin).");
    }
}
