<?php

namespace App\Actions\Profile;

use App\Models\User;

/**
 * Changes the name and email address of the signed-in account. A new
 * address must be verified again before the account can keep using the SPA
 * and the API, so the verification timestamp is cleared and a verification
 * email is sent to the new address.
 */
final class UpdateProfile
{
    public function handle(User $user, string $name, string $email): User
    {
        $user->fill(['name' => $name, 'email' => $email]);

        $emailChanged = $user->isDirty('email');
        if ($emailChanged) {
            $user->forceFill(['email_verified_at' => null]);
        }

        $user->save();

        if ($emailChanged) {
            $user->sendEmailVerificationNotification();
        }

        return $user;
    }
}
