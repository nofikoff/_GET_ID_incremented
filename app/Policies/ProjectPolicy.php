<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;

/**
 * No delete ability: projects are retired by is_active, never removed (FR-016).
 */
class ProjectPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Project $project): bool
    {
        return $user->isAdmin();
    }
}
