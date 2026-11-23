<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\ApiTokenCookieFactory;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * POST /api/change-password from the SPA keeps the browser that made the
 * change signed in and signs out the account's other browser sessions
 * (AuthenticateSession, see App\Http\Middleware\KeepSessionAfterPasswordChange).
 * laravel_token cookies issued before the change stop working
 * (App\Http\Middleware\RejectStaleApiTokenCookie).
 *
 * Each "browser" is a set of cookies (session and laravel_token) plus the
 * CSRF token of its page, sent explicitly with every request.
 */
class PasswordChangeSessionTest extends TestCase
{
    use RefreshDatabase;

    private const string NEW_PASSWORD = 'Rotated-password-123!';

    public function test_the_browser_that_changes_the_password_stays_signed_in_and_others_are_signed_out(): void
    {
        $user = User::factory()->create();
        $current = $this->signIn($user);
        $other = $this->signIn($user);

        $response = $this->changePasswordFrom($current)->assertOk();

        // The session keeps going under a new id; the old id no longer works.
        $newSession = $this->cookieValue($response, $this->sessionCookie());
        $this->assertNotSame($current['session'], $newSession);
        $this->visitDashboard($newSession)->assertOk();
        $this->visitDashboard($current['session'])->assertRedirect('/login');

        $this->visitDashboard($other['session'])->assertRedirect('/login');
    }

    public function test_a_session_cookie_from_another_browser_is_not_kept_signed_in(): void
    {
        $user = User::factory()->create();
        $current = $this->signIn($user);
        $other = $this->signIn($user);

        // Cookies and CSRF token of the current browser, session of the other.
        $response = $this->changePasswordFrom(['session' => $other['session']] + $current)->assertOk();

        $this->assertSame($other['session'], $this->cookieValue($response, $this->sessionCookie()));

        $this->visitDashboard($other['session'])->assertRedirect('/login');
    }

    public function test_a_failed_change_leaves_the_session_alone(): void
    {
        $user = User::factory()->create();
        $current = $this->signIn($user);

        $this->changePasswordFrom($current, 'wrong-password')->assertUnprocessable();

        $this->visitDashboard($current['session'])->assertOk();
    }

    public function test_bearer_token_requests_do_not_start_a_session(): void
    {
        app(ClientRepository::class)->createPersonalAccessGrantClient('Personal Access Client', 'users');
        $user = User::factory()->create();
        $token = $user->createToken('cli')->accessToken;

        $this->withToken($token)->postJson('/api/change-password', [
            'current_password' => UserFactory::PASSWORD,
            'new_password' => self::NEW_PASSWORD,
            'confirm_password' => self::NEW_PASSWORD,
        ])->assertOk()->assertCookieMissing($this->sessionCookie());
    }

    public function test_api_cookies_issued_before_a_password_change_stop_working(): void
    {
        $user = User::factory()->create();
        $current = $this->signIn($user);
        $other = $this->signIn($user);
        $this->getProfile($other)->assertOk();

        $response = $this->changePasswordFrom($current)->assertOk();

        $this->getProfile($other)->assertUnauthorized();
        $this->getProfile($current)->assertUnauthorized();

        // The browser that made the change received a fresh cookie, and its
        // next page load issues one as well.
        $fresh = ['token' => $this->cookieValue($response, Passport::cookie())] + $current;
        $this->getProfile($fresh)->assertOk()->assertJsonPath('data.id', $user->id);
        $page = $this->visitDashboard($this->cookieValue($response, $this->sessionCookie()))->assertOk();
        $this->getProfile(['token' => $this->cookieValue($page, Passport::cookie())] + $current)->assertOk();
    }

    public function test_api_cookies_without_the_password_claim_are_rejected(): void
    {
        $user = User::factory()->create();
        $browser = $this->signIn($user);
        // A cookie as Passport issues it by default, without the claim.
        $passportCookie = new ApiTokenCookieFactory(app('config'), app(Encrypter::class));

        $this->getProfile(['token' => (string) $passportCookie->make($user->id, $browser['csrf'])->getValue()] + $browser)
            ->assertUnauthorized();
        $this->getProfile($browser)->assertOk();
    }

    public function test_a_stale_api_cookie_does_not_block_public_routes_or_bearer_tokens(): void
    {
        app(ClientRepository::class)->createPersonalAccessGrantClient('Personal Access Client', 'users');
        $user = User::factory()->create();
        $other = $this->signIn($user);
        $this->changePasswordFrom($this->signIn($user))->assertOk();

        $this->getProfile($other, '/api/version')->assertOk();

        // The guard uses the bearer token and ignores the cookie.
        $this->newBrowserRequest();
        $this->withCredentials()->withCookie(Passport::cookie(), $other['token'])
            ->withToken($user->createToken('cli')->accessToken)
            ->getJson('/api/profile')->assertOk()->assertJsonPath('data.id', $user->id);
    }

    /**
     * Signs in through the login form and opens the dashboard, like a new
     * browser would.
     *
     * @return array{session: string, token: string, csrf: string}
     */
    private function signIn(User $user): array
    {
        $this->newBrowserRequest();
        $login = $this->post('/login', ['email' => $user->email, 'password' => UserFactory::PASSWORD])
            ->assertRedirect('/dashboard');
        $session = $this->cookieValue($login, $this->sessionCookie());

        $page = $this->visitDashboard($session)->assertOk();

        return [
            'session' => $session,
            'token' => $this->cookieValue($page, Passport::cookie()),
            'csrf' => (string) app('session.store')->token(),
        ];
    }

    /**
     * @param  array{session: string, token: string, csrf: string}  $browser
     * @return TestResponse<Response>
     */
    private function changePasswordFrom(array $browser, string $currentPassword = UserFactory::PASSWORD): TestResponse
    {
        $this->newBrowserRequest();

        return $this->withCredentials()
            ->withCookies([$this->sessionCookie() => $browser['session'], Passport::cookie() => $browser['token']])
            ->withHeader('X-CSRF-TOKEN', $browser['csrf'])
            ->postJson('/api/change-password', [
                'current_password' => $currentPassword,
                'new_password' => self::NEW_PASSWORD,
                'confirm_password' => self::NEW_PASSWORD,
            ]);
    }

    /**
     * An API request authenticated like the SPA: laravel_token cookie and
     * CSRF header, no session cookie.
     *
     * @param  array{session: string, token: string, csrf: string}  $browser
     * @return TestResponse<Response>
     */
    private function getProfile(array $browser, string $uri = '/api/profile'): TestResponse
    {
        $this->newBrowserRequest();

        return $this->withCredentials()
            ->withCookie(Passport::cookie(), $browser['token'])
            ->withHeader('X-CSRF-TOKEN', $browser['csrf'])
            ->getJson($uri);
    }

    /**
     * @return TestResponse<Response>
     */
    private function visitDashboard(string $session): TestResponse
    {
        $this->newBrowserRequest();

        return $this->withCookie($this->sessionCookie(), $session)->get('/dashboard');
    }

    /**
     * Forgets everything the previous request left behind in the test
     * application: cookies and headers, the authenticated users, the default
     * guard ("auth:api" switches it to "api") and the session data in memory.
     * Stored sessions stay in the session handler.
     */
    private function newBrowserRequest(): void
    {
        $this->flushHeaders();
        $this->defaultCookies = [];
        $this->withCredentials = false;
        $this->app['auth']->forgetGuards();
        $this->app['auth']->shouldUse('web');
        app('session.store')->flush();
    }

    private function sessionCookie(): string
    {
        $name = config('session.cookie');
        $this->assertIsString($name);

        return $name;
    }

    /**
     * @param  TestResponse<Response>  $response
     */
    private function cookieValue(TestResponse $response, string $name): string
    {
        $value = $response->assertCookie($name)->getCookie($name)?->getValue();
        $this->assertIsString($value);

        return $value;
    }
}
