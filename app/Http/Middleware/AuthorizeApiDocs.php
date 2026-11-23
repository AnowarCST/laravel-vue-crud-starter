<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Redirect;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards /docs and /docs/openapi.yaml (see config/api-docs.php):
 *
 * - disabled (API_DOCS_ENABLED=false): 404, whoever asks;
 * - local environment: open to everyone, so the docs work before any
 *   account exists;
 * - any other environment: signed-in (web session), verified accounts that
 *   pass the "viewApiDocs" gate (administrators). Guests are sent to the
 *   login page, unverified accounts to the verification notice, and other
 *   accounts receive 403.
 */
class AuthorizeApiDocs
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(config()->boolean('api-docs.enabled'), 404);

        if (app()->environment('local')) {
            return $next($request);
        }

        $user = $request->user();

        if ($user === null) {
            // Rendered by the exception handler: a redirect to /login that
            // returns here after signing in, or 401 for JSON requests.
            throw new AuthenticationException;
        }

        if (! $user->hasVerifiedEmail()) {
            abort_if($request->expectsJson(), 403, 'Your email address is not verified.');

            return Redirect::route('verification.notice');
        }

        Gate::forUser($user)->authorize('viewApiDocs');

        return $next($request);
    }
}
