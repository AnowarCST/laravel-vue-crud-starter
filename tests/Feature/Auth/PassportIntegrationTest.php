<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;
use Tests\TestCase;

class PassportIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_spa_receives_laravel_token_cookie_usable_with_csrf_header_only(): void
    {
        $user = User::factory()->create();

        $page = $this->actingAs($user)->get('/dashboard')->assertOk()->assertCookie(Passport::cookie());
        $jwt = (string) $page->getCookie(Passport::cookie())?->getValue();
        $csrf = session()->token();

        // Fresh application state: authenticate purely via the cookie.
        $this->app['auth']->forgetGuards();

        $this->withCredentials()->withCookie(Passport::cookie(), $jwt)
            ->withHeader('X-CSRF-TOKEN', $csrf)
            ->getJson('/api/profile')
            ->assertOk()->assertJsonPath('data.id', $user->id);

        $this->app['auth']->forgetGuards();
        $this->withCredentials()->withCookie(Passport::cookie(), $jwt)
            ->withHeader('X-CSRF-TOKEN', 'forged')
            ->getJson('/api/profile')
            ->assertUnauthorized();
    }

    public function test_json_api_routes_for_developer_page_are_registered_and_session_protected(): void
    {
        foreach (['/oauth/scopes', '/oauth/clients', '/oauth/tokens', '/oauth/personal-access-tokens'] as $uri) {
            $this->getJson($uri)->assertUnauthorized();
        }

        $this->actingAs(User::factory()->create());
        foreach (['/oauth/scopes', '/oauth/clients', '/oauth/tokens', '/oauth/personal-access-tokens'] as $uri) {
            $this->getJson($uri)->assertOk();
        }
    }

    public function test_personal_access_token_lifecycle_and_revocation_on_password_change(): void
    {
        app(ClientRepository::class)->createPersonalAccessGrantClient('Personal Access Client', 'users');
        $user = User::factory()->create();

        $token = $this->jsonString($this->actingAs($user)->postJson('/oauth/personal-access-tokens', ['name' => 'cli'])
            ->assertOk(), 'accessToken');
        $this->assertNotEmpty($token);

        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/profile')->assertOk()->assertJsonPath('data.id', $user->id);

        $this->app['auth']->forgetGuards();
        $this->withToken($token)->postJson('/api/change-password', [
            'current_password' => UserFactory::PASSWORD,
            'new_password' => 'Rotated-password-123!',
            'confirm_password' => 'Rotated-password-123!',
        ])->assertOk();

        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/profile')->assertUnauthorized();
    }

    public function test_deleting_a_user_revokes_their_tokens(): void
    {
        app(ClientRepository::class)->createPersonalAccessGrantClient('Personal Access Client', 'users');
        $user = User::factory()->create();
        $tokenId = $user->createToken('cli')->getToken()?->getKey();
        $this->assertNotNull($tokenId);

        $this->actingAs(User::factory()->admin()->create(), 'api')
            ->deleteJson('/api/user/'.$user->id)->assertOk();

        $this->assertDatabaseHas('oauth_access_tokens', ['id' => $tokenId, 'revoked' => true]);
    }
}
