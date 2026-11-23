<?php

namespace App\Policies;

use App\Models\Category;
use App\Models\User;

/**
 * Any authenticated user may browse and maintain the catalogue; only
 * administrators may delete entries.
 */
class CategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Category $model): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Category $model): bool
    {
        return true;
    }

    public function delete(User $user, Category $model): bool
    {
        return $user->isAdmin();
    }
}
