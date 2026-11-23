<?php

namespace Tests\Feature;

use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class RateLimitingTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_locks_out_an_account_after_five_failures(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => $user->email, 'password' => 'wrong-password'])
                ->assertSessionHasErrors('email');
        }

        // Even the correct password is refused while locked out.
        $this->post('/login', ['email' => $user->email, 'password' => 'Test-password-123!'])
            ->assertSessionHasErrors('email');
        $errors = session('errors');
        $this->assertInstanceOf(ViewErrorBag::class, $errors);
        $this->assertStringStartsWith('Too many login attempts.', $errors->first('email'));
        $this->postJson('/login', ['email' => $user->email, 'password' => 'Test-password-123!'])
            ->assertStatus(429);
        $this->assertGuest();
    }

    public function test_login_submissions_are_capped_per_ip_across_accounts(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->post('/login', ['email' => "nobody{$i}@example.com", 'password' => 'wrong-password'])
                ->assertRedirect();
        }

        $this->post('/login', ['email' => 'another@example.com', 'password' => 'wrong-password'])
            ->assertStatus(429);
    }

    public function test_failed_login_does_not_flash_the_password(): void
    {
        $this->from('/login')->post('/login', ['email' => 'nobody@example.com', 'password' => 'Secret-password-123!'])
            ->assertRedirect('/login')
            ->assertSessionHasErrors('email');

        $this->assertSame('nobody@example.com', session()->getOldInput('email'));
        $this->assertNull(session()->getOldInput('password'));
    }

    public function test_registration_is_rate_limited(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/register', ['name' => 'x'])->assertSessionHasErrors();
        }

        $this->post('/register', ['name' => 'x'])->assertStatus(429);
    }

    public function test_password_reset_requests_are_rate_limited(): void
    {
        Notification::fake();

        for ($i = 0; $i < 5; $i++) {
            $this->post('/password/email', ['email' => "someone{$i}@example.com"])->assertRedirect();
        }

        $this->post('/password/email', ['email' => 'someone@example.com'])->assertStatus(429);
        $this->post('/password/reset', ['email' => 'someone@example.com', 'token' => 'x'])->assertStatus(429);
    }

    public function test_password_reset_request_does_not_reveal_whether_an_account_exists(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $known = $this->from('/password/reset')->post('/password/email', ['email' => $user->email]);
        $unknown = $this->from('/password/reset')->post('/password/email', ['email' => 'missing@example.com']);
        // Second request for the same account is throttled by the broker.
        $throttled = $this->from('/password/reset')->post('/password/email', ['email' => $user->email]);

        foreach ([$known, $unknown, $throttled] as $response) {
            $response->assertRedirect('/password/reset')
                ->assertSessionHasNoErrors()
                ->assertSessionHas('status', ForgotPasswordController::GENERIC_STATUS);
        }
        Notification::assertSentToTimes($user, ResetPassword::class, 1);
    }

    public function test_password_reset_failure_is_generic_for_unknown_emails_and_bad_tokens(): void
    {
        $user = User::factory()->create();
        $payload = ['token' => 'invalid', 'password' => 'Brand-new-password-1!', 'password_confirmation' => 'Brand-new-password-1!'];

        $existing = $this->post('/password/reset', ['email' => $user->email] + $payload);
        $missing = $this->post('/password/reset', ['email' => 'missing@example.com'] + $payload);

        $existing->assertSessionHasErrors(['email' => 'This password reset link is invalid or has expired.']);
        $missing->assertSessionHasErrors(['email' => 'This password reset link is invalid or has expired.']);
    }

    public function test_api_requests_are_rate_limited_per_user(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'api');

        for ($i = 0; $i < 60; $i++) {
            $this->getJson('/api/category')->assertOk();
        }

        $this->getJson('/api/category')->assertStatus(429)->assertHeader('Retry-After');

        // A different account has its own budget.
        $this->actingAs(User::factory()->create(), 'api')->getJson('/api/category')->assertOk();
    }
}
