<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Auth;
use Laravel\Passport\TransientToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps the browser session that changes its own password signed in.
 *
 * The SPA sends POST /api/change-password with the laravel_token cookie, so
 * the request runs under the Passport "api" guard, without a session. The
 * browser's web session would keep the old password hash, and
 * AuthenticateSession (bootstrap/app.php) would sign it out on the next page
 * load, like every other session of the account.
 *
 * For requests authenticated with the laravel_token cookie, this middleware
 * opens the browser's web session (EncryptCookies decrypts the session cookie,
 * StartSession loads it). If that session belongs to the same account, was
 * signed in with the current password and issued the CSRF token the request
 * carries, and the password changed during the request, it stores the new
 * password hash in that session and gives the session a new id, as
 * SessionGuard::login() does. Other sessions keep the old hash and are signed
 * out on their next page load. Bearer-token requests stay stateless.
 *
 * It must run after "auth:api": EncryptCookies decrypts every cookie, and
 * Passport's guard decrypts laravel_token itself.
 */
final class KeepSessionAfterPasswordChange
{
    /**
     * The session guard whose stored password hash AuthenticateSession checks
     * on web requests (config auth.defaults.guard).
     */
    private const string WEB_GUARD = 'web';

    public function __construct(
        private readonly EncryptCookies $encryptCookies,
        private readonly StartSession $startSession,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('api');

        if (! $user instanceof User || ! $user->token() instanceof TransientToken) {
            /** @var Response */
            return $next($request);
        }

        /** @var Response */
        return $this->encryptCookies->handle($request, function (Request $request) use ($next, $user): Response {
            /** @var Response */
            return $this->startSession->handle(
                $request,
                fn (Request $request): Response => $this->handleWithSession($request, $next, $user),
            );
        });
    }

    private function handleWithSession(Request $request, Closure $next, User $user): Response
    {
        $guard = Auth::guard(self::WEB_GUARD);
        $session = $request->session();
        $hashKey = 'password_hash_'.self::WEB_GUARD;
        $oldPassword = $user->getAuthPassword();

        $isThisBrowsersSession = in_array($session->get($guard->getName()), [$user->id, (string) $user->id], true)
            && is_string($storedHash = $session->get($hashKey))
            && hash_equals($guard->hashPasswordForCookie($oldPassword), $storedHash)
            && is_string($csrfToken = $request->header('X-CSRF-TOKEN'))
            && hash_equals((string) $session->token(), $csrfToken);

        /** @var Response $response */
        $response = $next($request);

        if ($isThisBrowsersSession && $user->getAuthPassword() !== $oldPassword) {
            $session->put($hashKey, $guard->hashPasswordForCookie($user->getAuthPassword()));
            $session->migrate(true);
        }

        return $response;
    }
}
