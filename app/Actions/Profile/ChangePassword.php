<?php

namespace App\Actions\Profile;

use App\Models\User;

/**
 * Sets a new password for the signed-in account. The "hashed" cast hashes
 * it, and User::booted() then rotates the remember token and revokes every
 * OAuth access and refresh token of the account.
 */
final class ChangePassword
{
    public function handle(User $user, string $newPassword): void
    {
        $user->update(['password' => $newPassword]);
    }
}
