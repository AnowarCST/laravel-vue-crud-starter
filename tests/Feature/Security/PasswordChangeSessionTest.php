<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * POST /api/change-password from the SPA keeps the browser that made the
 * change signed in and signs out the account's other browser sessions
 * (AuthenticateSession, see App\Http\Middleware\KeepSessionAfterPasswordChange).
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
