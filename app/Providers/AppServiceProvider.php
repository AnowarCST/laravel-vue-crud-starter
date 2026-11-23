<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
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
        $this->configureAuthorization();
        $this->configureRateLimiting();
    }

    protected function configureProxies(): void
    {
        $proxies = trim((string) config('app.trusted_proxies'));

        if ($proxies !== '') {
            TrustProxies::at($proxies === '*' ? '*' : array_map('trim', explode(',', $proxies)));
        }
    }

    protected function configureAuthorization(): void
    {
        // Model policies (App\Policies\*Policy) are auto-discovered. These
        // gates remain for coarse role checks.
        Gate::define('isAdmin', fn (User $user): bool => $user->isAdmin());
        Gate::define('isUser', fn (User $user): bool => $user->type === User::TYPE_USER);
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
