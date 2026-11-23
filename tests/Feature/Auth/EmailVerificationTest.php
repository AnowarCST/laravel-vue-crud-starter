<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Laravel\Passport\Http\Middleware\CreateFreshApiToken;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_sends_exactly_one_verification_email(): void
    {
        Notification::fake();
        $this->withoutMiddleware(CreateFreshApiToken::class);

        $this->post('/register', [
            'name' => 'New User', 'email' => 'new@example.com',
            'password' => 'Registration-password-123!',
            'password_confirmation' => 'Registration-password-123!',
        ])->assertRedirect('/dashboard');

        $user = User::where('email', 'new@example.com')->firstOrFail();
        $this->assertFalse($user->hasVerifiedEmail());
        Notification::assertSentToTimes($user, VerifyEmail::class, 1);
        Notification::assertCount(1);

        // The SPA is not available until the address is verified.
        $this->get('/dashboard')->assertRedirect('/email/verify');
        $this->get('/email/verify')->assertOk();
    }

    public function test_unverified_users_cannot_use_the_spa(): void
    {
        $this->actingAs(User::factory()->unverified()->create());
        $this->get('/dashboard')->assertRedirect('/email/verify');
        $this->get('/products/12')->assertRedirect('/email/verify');
    }

    /**
     * Including ids that do not exist: the answer must not reveal them.
     *
     * @return iterable<string, array{string, string}>
     */
    public static function apiRequests(): iterable
    {
        foreach ([
            ['GET', '/api/profile'], ['PUT', '/api/profile'], ['POST', '/api/change-password'],
            ['GET', '/api/tag/list'], ['GET', '/api/category/list'], ['POST', '/api/product/upload'],
            ['GET', '/api/product/stats'], ['GET', '/api/user'], ['GET', '/api/user/1'],
            ['GET', '/api/product'], ['POST', '/api/product'], ['GET', '/api/product/999'],
            ['PUT', '/api/product/999'], ['GET', '/api/category'], ['PATCH', '/api/category/999'],
            ['DELETE', '/api/tag/1'],
        ] as [$method, $uri]) {
            yield $method.' '.$uri => [$method, $uri];
        }
    }

    #[DataProvider('apiRequests')]
    public function test_unverified_users_cannot_use_the_api(string $method, string $uri): void
    {
        $this->actingAsUnverifiedUser();

        $this->assertEmailNotVerified($this->json($method, $uri));
        $this->getJson('/api/version')->assertOk();
    }

    public function test_every_authenticated_api_route_requires_a_verified_email(): void
    {
        $apiRoutes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn (RoutingRoute $route): bool => str_starts_with($route->uri(), 'api/'));

        $this->assertNotEmpty($apiRoutes);

        foreach ($apiRoutes as $route) {
            $middleware = $route->gatherMiddleware();

            if ($route->uri() === 'api/version') {
                $this->assertNotContains('verified', $middleware);
                $this->assertNotContains('auth:api', $middleware);

                continue;
            }

            $this->assertContains('auth:api', $middleware, $route->uri());
            $this->assertContains('verified', $middleware, $route->uri());
        }
    }

    public function test_verification_link_unlocks_the_spa_and_the_api(): void
    {
        $user = User::factory()->unverified()->create();
        $url = URL::temporarySignedRoute('verification.verify', now()->addHour(), [
            'id' => $user->getKey(), 'hash' => sha1($user->getEmailForVerification()),
        ]);

        $this->actingAs($user)->get($url)->assertRedirect('/dashboard');

        $this->assertTrue($user->refresh()->hasVerifiedEmail());
        $this->get('/dashboard')->assertOk();
        $this->actingAs($user->refresh(), 'api')->getJson('/api/profile')->assertOk();
    }

    public function test_verification_link_must_be_signed_and_match_the_account(): void
    {
        $user = User::factory()->unverified()->create();
        $this->actingAs($user);

        $this->get('/email/verify/'.$user->id.'/'.sha1($user->email))->assertForbidden();
        $this->get(URL::temporarySignedRoute('verification.verify', now()->addHour(), [
            'id' => $user->getKey(), 'hash' => sha1('someone-else@example.com'),
        ]))->assertForbidden();

        $this->assertFalse($user->refresh()->hasVerifiedEmail());
    }

    public function test_resending_the_verification_email_is_throttled(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();
        $this->actingAs($user);

        for ($i = 0; $i < 6; $i++) {
            $this->from('/email/verify')->post('/email/resend')->assertRedirect('/email/verify');
        }
        $this->from('/email/verify')->post('/email/resend')->assertTooManyRequests();

        Notification::assertSentToTimes($user, VerifyEmail::class, 6);
    }

    public function test_accounts_created_by_an_administrator_are_verified_without_an_email(): void
    {
        Notification::fake();
        $this->actingAsAdmin();

        $id = $this->jsonInt($this->postJson('/api/user', [
            'type' => 'user', 'name' => 'Staff', 'email' => 'staff@example.com',
            'password' => 'Staff-password-123!',
        ])->assertOk(), 'data.id');

        $created = User::query()->whereKey($id)->firstOrFail();
        $this->assertTrue($created->hasVerifiedEmail());
        Notification::assertNothingSent();
        $this->actingAs($created, 'api')->getJson('/api/profile')->assertOk();
    }

    public function test_changing_the_profile_email_requires_verifying_the_new_address(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $this->actingAs($user, 'api');

        // Updating only the name keeps the account verified.
        $this->putJson('/api/profile', ['name' => 'Renamed', 'email' => $user->email])->assertOk();
        $this->assertTrue($user->refresh()->hasVerifiedEmail());
        Notification::assertNothingSent();

        $this->putJson('/api/profile', ['name' => 'Renamed', 'email' => 'changed@example.com'])
            ->assertOk()
            ->assertJsonPath('data.email', 'changed@example.com')
            ->assertJsonPath('data.email_verified_at', null);

        $user->refresh();
        $this->assertFalse($user->hasVerifiedEmail());
        Notification::assertSentToTimes($user, VerifyEmail::class, 1);
        Notification::assertSentTo($user, VerifyEmail::class, function (VerifyEmail $notification, array $channels, User $notifiable): bool {
            return $notifiable->getEmailForVerification() === 'changed@example.com';
        });

        $this->assertEmailNotVerified($this->actingAs($user, 'api')->getJson('/api/profile'));
    }

    public function test_upgrade_migration_marks_existing_accounts_as_verified(): void
    {
        $unverified = User::factory()->unverified()->count(2)->create();
        $verifiedAt = now()->subYear()->startOfSecond();
        $verified = User::factory()->create(['email_verified_at' => $verifiedAt]);

        $migration = require database_path('migrations/2026_11_23_000002_mark_existing_users_as_verified.php');
        $migration->up();

        foreach ($unverified as $user) {
            $this->assertTrue($user->refresh()->hasVerifiedEmail());
        }
        $this->assertEquals($verifiedAt, $verified->refresh()->email_verified_at);
        $this->assertSame(0, DB::table('users')->whereNull('email_verified_at')->count());
    }
}
