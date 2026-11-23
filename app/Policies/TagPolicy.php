<?php

namespace App\Policies;

use App\Models\Tag;
use App\Models\User;

/**
 * Any authenticated user may browse and maintain the catalogue; only
 * administrators may delete entries.
 */
class TagPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Tag $model): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Tag $model): bool
    {
        return true;
    }

    public function delete(User $user, Tag $model): bool
    {
        return $user->isAdmin();
    }
}
