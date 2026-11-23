<?php

namespace App\Providers;

use App\Auth\PasswordBoundApiTokenCookieFactory;
use App\Models\User;
use App\Rules\PasswordByteLength;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Laravel\Passport\ApiTokenCookieFactory;
use Laravel\Passport\Passport;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Passport registers its routes while booting, which happens before
        // this provider boots, so route-affecting flags are set here.
        $this->configurePassport();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureProxies();
        $this->configureRateLimiting();
        $this->configureAuthorization();
        $this->configurePasswords();
    }

    /**
     * Policies are linked to their models with #[UsePolicy] on the model.
     */
    protected function configureAuthorization(): void
    {
        // Outside the local environment, /docs is limited to administrators
        // (see App\Http\Middleware\AuthorizeApiDocs).
        Gate::define('viewApiDocs', fn (User $user): bool => $user->isAdmin());
    }

    /**
     * The rule behind Password::defaults(), used wherever a password is set:
     * 12 to 72 characters and at most 72 bytes, the bcrypt input limit
     * (Laravel's max rule counts characters, not bytes).
     */
    protected function configurePasswords(): void
    {
        Password::defaults(fn (): Password => Password::min(12)->max(72)->rules([new PasswordByteLength]));
    }

    protected function configureProxies(): void
    {
        $proxies = config('app.trusted_proxies');
        $proxies = is_string($proxies) ? trim($proxies) : '';

        if ($proxies !== '') {
            TrustProxies::at($proxies === '*' ? '*' : array_map('trim', explode(',', $proxies)));
        }
    }

    protected function configurePassport(): void
    {
        // The SPA's "Developer" page manages personal access tokens and OAuth
        // clients through Passport's (deprecated, opt-in) JSON API.
        Passport::$registersJsonApiRoutes = true;

        // Passport 13 is headless: point the authorization-code consent screen
        // at the application's view. The device authorization grant has no UI
        // in this application, so its routes are not registered.
        Passport::authorizationView('vendor.passport.authorize');
        Passport::$deviceCodeGrantEnabled = false;

        // Databases created with Passport <= 12 migrations use integer client
        // ids; set PASSPORT_CLIENT_UUIDS=false for those until migrated.
        Passport::$clientUuids = (bool) config('passport.client_uuids', true);

        // The SPA's laravel_token cookie carries a claim derived from the
        // password hash, so a password change invalidates the cookies issued
        // before it (see App\Http\Middleware\RejectStaleApiTokenCookie).
        $this->app->bind(ApiTokenCookieFactory::class, PasswordBoundApiTokenCookieFactory::class);

        Passport::tokensExpireIn(now()->addDays(15));
        Passport::refreshTokensExpireIn(now()->addDays(30));
        Passport::personalAccessTokensExpireIn(now()->addMonths(6));
    }

    protected function configureRateLimiting(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->getAuthIdentifier() ?: $request->ip());
        });

        // Per-IP cap on login submissions. AuthenticatesUsers additionally
        // locks out an email+IP pair after 5 failures per minute.
        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(10)->by('login|'.$request->ip());
        });

        RateLimiter::for('register', function (Request $request) {
            return Limit::perMinute(5)->by('register|'.$request->ip());
        });

        RateLimiter::for('password-reset', function (Request $request) {
            return [
                Limit::perMinute(5)->by('password-reset|'.$request->ip()),
                Limit::perHour(10)->by('password-reset-email|'.Str::lower(
                    is_string($email = $request->input('email')) ? $email : ''
                )),
            ];
        });
    }
}
