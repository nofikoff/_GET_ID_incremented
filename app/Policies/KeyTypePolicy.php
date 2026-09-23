<?php

namespace App\Policies;

use App\Models\KeyType;
use App\Models\User;

/**
 * No delete ability: key types are retired by is_active, never removed (FR-016).
 */
class KeyTypePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, KeyType $keyType): bool
    {
        return $user->isAdmin();
    }
}
