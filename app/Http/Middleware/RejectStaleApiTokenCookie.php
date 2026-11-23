<?php

namespace App\Http\Middleware;

use App\Auth\PasswordBoundApiTokenCookieFactory;
use Closure;
use Exception;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Http\Request;
use Laravel\Passport\Passport;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ignores a laravel_token cookie that was issued before the account's
 * password last changed (see PasswordBoundApiTokenCookieFactory), so the
 * request is treated as unauthenticated and "auth:api" answers 401.
 *
 * It runs before authentication (see bootstrap/app.php) and decodes the
 * cookie the way Passport's TokenGuard does. A cookie that cannot be decoded
 * is left alone for the guard to reject. Requests with a bearer token are not
 * touched: the guard ignores the cookie for them, and access tokens are
 * revoked in the database when the password changes (User::booted()).
 */
final class RejectStaleApiTokenCookie
{
    public function __construct(
        private readonly Encrypter $encrypter,
        private readonly PasswordBoundApiTokenCookieFactory $cookieFactory,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $cookie = $request->cookies->get(Passport::cookie());

        if (is_string($cookie) && ! $request->bearerToken()) {
            $claims = $this->decode($cookie);

            if ($claims !== null && ! $this->hasCurrentPasswordClaim($claims)) {
                $request->cookies->remove(Passport::cookie());
            }
        }

        /** @var Response */
        return $next($request);
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    private function hasCurrentPasswordClaim(array $claims): bool
    {
        $userId = $claims['sub'] ?? null;
        $claim = $claims[PasswordBoundApiTokenCookieFactory::PASSWORD_CLAIM] ?? null;

        if ((! is_string($userId) && ! is_int($userId)) || ! is_string($claim)) {
            return false;
        }

        $expected = $this->cookieFactory->passwordClaimFor($userId);

        return $expected !== null && hash_equals($expected, $claim);
    }

    /**
     * The cookie's claims, or null when it cannot be decrypted or its
     * signature or expiry is invalid (Laravel\Passport\Guards\TokenGuard).
     *
     * @return array<string, mixed>|null
     */
    private function decode(string $cookie): ?array
    {
        try {
            $jwt = $cookie;

            if (Passport::$decryptsCookies) {
                $decrypted = $this->encrypter->decrypt($cookie, Passport::$unserializesCookies);

                if (! is_string($decrypted)) {
                    return null;
                }

                $jwt = CookieValuePrefix::remove($decrypted);
            }

            /** @var array<string, mixed> */
            return (array) JWT::decode($jwt, new Key(Passport::tokenEncryptionKey($this->encrypter), 'HS256'));
        } catch (Exception) {
            return null;
        }
    }
}
