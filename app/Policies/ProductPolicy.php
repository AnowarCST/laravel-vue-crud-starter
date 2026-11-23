<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;

/**
 * Any authenticated user may browse and maintain the catalogue; only
 * administrators may delete entries.
 */
class ProductPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Product $model): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Product $model): bool
    {
        return true;
    }

    public function delete(User $user, Product $model): bool
    {
        return $user->isAdmin();
    }
}
