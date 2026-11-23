<?php

use App\Http\Middleware\RejectStaleApiTokenCookie;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Laravel\Passport\Http\Middleware\CreateFreshApiToken;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Security headers (CSP with a per-request nonce, nosniff, framing...)
        // are applied to every response, including error pages.
        $middleware->append(SecurityHeaders::class);

        // Invalidate other sessions when the password hash changes.
        $middleware->authenticateSessions();

        // The SPA calls /api/* with the "laravel_token" cookie issued here and
        // the X-CSRF-TOKEN header; the "auth:api" (Passport) guard validates both.
        $middleware->web(append: [CreateFreshApiToken::class]);

        // A laravel_token cookie issued before the last password change no
        // longer authenticates API requests. This has to run before the guard.
        $middleware->api(prepend: [RejectStaleApiTokenCookie::class]);
        $middleware->prependToPriorityList(before: AuthenticatesRequests::class, prepend: RejectStaleApiTokenCookie::class);

        // Rate limit every API route (limiter defined in AppServiceProvider).
        $middleware->throttleApi('api');

        // Check the email verification before route model binding looks the
        // record up, so unverified accounts get 403 and never learn which
        // ids exist (404). Authentication already runs before binding.
        $middleware->prependToPriorityList(before: SubstituteBindings::class, prepend: EnsureEmailIsVerified::class);

        // Passwords must be validated exactly as typed.
        $middleware->trimStrings(except: ['new_password', 'confirm_password']);

        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo('/dashboard');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontFlash([
            'current_password',
            'password',
            'password_confirmation',
            'new_password',
            'confirm_password',
        ]);

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Missing models are converted to 404s whose message names the model
        // class; keep API error bodies generic unless debugging locally.
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if (! config('app.debug') && ($request->is('api/*') || $request->expectsJson())) {
                return response()->json(['message' => 'Not Found.'], 404);
            }
        });
    })->create();
