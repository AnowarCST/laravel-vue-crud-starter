<?php

namespace App\Auth;

use App\Models\User;
use Firebase\JWT\JWT;
use Laravel\Passport\ApiTokenCookieFactory;
use Laravel\Passport\Passport;

/**
 * Issues Passport's laravel_token cookie (CreateFreshApiToken) with one more
 * claim, "pwd": an HMAC of the account's password hash, keyed with the same
 * key Passport signs the cookie with (APP_KEY by default). The hash itself
 * is never put in the cookie.
 *
 * Passport's cookie is a signed JWT that cannot be revoked before it expires.
 * App\Http\Middleware\RejectStaleApiTokenCookie compares this claim with the
 * current password hash, so cookies issued before a password change stop
 * working as soon as the password changes.
 */
class PasswordBoundApiTokenCookieFactory extends ApiTokenCookieFactory
{
    public const string PASSWORD_CLAIM = 'pwd';

    /**
     * The claim for the account's current password, or null when the account
     * does not exist or has no password.
     */
    public function passwordClaimFor(string|int $userId): ?string
    {
        $passwordHash = User::query()->whereKey($userId)->value('password');

        if (! is_string($passwordHash) || $passwordHash === '') {
            return null;
        }

        return hash_hmac('sha256', 'laravel_token|'.$passwordHash, Passport::tokenEncryptionKey($this->encrypter));
    }

    protected function createToken(string|int $userId, string $csrfToken, int $expiration): string
    {
        return JWT::encode([
            'sub' => $userId,
            'csrf' => $csrfToken,
            'exp' => $expiration,
            self::PASSWORD_CLAIM => $this->passwordClaimFor($userId),
        ], Passport::tokenEncryptionKey($this->encrypter), 'HS256');
    }
}
