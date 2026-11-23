<?php

namespace App\Actions\Users;

use App\Enums\UserType;
use App\Models\User;

/**
 * Creates an account. The type is set explicitly here because it is not
 * mass assignable. Accounts created by an administrator or an operator are
 * marked as verified, because that person chose the address; self-registered
 * accounts are not, so they receive a verification email.
 */
final class CreateUser
{
    /**
     * @param  string  $password  Plain text; the model's "hashed" cast hashes it.
     */
    public function handle(string $name, string $email, string $password, UserType $type, bool $markEmailAsVerified): User
    {
        $user = new User(['name' => $name, 'email' => $email, 'password' => $password]);
        $user->forceFill([
            'type' => $type,
            'email_verified_at' => $markEmailAsVerified ? now() : null,
        ])->save();

        return $user;
    }
}
