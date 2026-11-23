<?php

namespace App\Policies;

use App\Models\User;

/**
 * Account management is restricted to administrators. Regular users manage
 * their own account exclusively through the /api/profile endpoints.
 */
class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->isAdmin();
    }

    public function view(User $actor, User $user): bool
    {
        return $actor->isAdmin();
    }

    public function create(User $actor): bool
    {
        return $actor->isAdmin();
    }

    public function update(User $actor, User $user): bool
    {
        return $actor->isAdmin();
    }

    /**
     * Administrators cannot delete their own account (prevents lock-out).
     */
    public function delete(User $actor, User $user): bool
    {
        return $actor->isAdmin() && ! $actor->is($user);
    }
}
