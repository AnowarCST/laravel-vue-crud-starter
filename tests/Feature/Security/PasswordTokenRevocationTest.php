<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Laravel\Passport\Http\Middleware\CreateFreshApiToken;
use Tests\TestCase;

class PasswordTokenRevocationTest extends TestCase
{
    use RefreshDatabase;

    private function issueToken(User $user): string
    {
        $clientId = (string) Str::uuid();
        $tokenId = Str::random(80);
        DB::table('oauth_clients')->insert([
            'id' => $clientId, 'name' => 'Test client', 'provider' => 'users',
            'redirect_uris' => '[]', 'grant_types' => '["authorization_code","refresh_token"]',
            'revoked' => false,
        ]);
        DB::table('oauth_access_tokens')->insert([
            'id' => $tokenId, 'user_id' => $user->id, 'client_id' => $clientId,
            'scopes' => '[]', 'revoked' => false, 'expires_at' => now()->addHour(),
        ]);
        DB::table('oauth_refresh_tokens')->insert([
            'id' => Str::random(80), 'access_token_id' => $tokenId,
            'revoked' => false, 'expires_at' => now()->addDay(),
        ]);

        return $tokenId;
    }

    public function test_password_rotation_revokes_only_that_users_access_and_refresh_tokens(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $tokenId = $this->issueToken($user);
        $otherTokenId = $this->issueToken($other);
        $oldRememberToken = $user->remember_token;
        $this->actingAs($user, 'api')->postJson('/api/change-password', [
            'current_password' => UserFactory::PASSWORD, 'new_password' => 'Rotated-password-123!',
            'confirm_password' => 'Rotated-password-123!',
        ])->assertOk();
        $this->assertDatabaseHas('oauth_access_tokens', ['id' => $tokenId, 'revoked' => true]);
        $this->assertDatabaseHas('oauth_refresh_tokens', ['access_token_id' => $tokenId, 'revoked' => true]);
        $this->assertDatabaseHas('oauth_access_tokens', ['id' => $otherTokenId, 'revoked' => false]);
        $this->assertDatabaseHas('oauth_refresh_tokens', ['access_token_id' => $otherTokenId, 'revoked' => false]);
        $this->assertNotSame($oldRememberToken, $user->refresh()->remember_token);
    }

    public function test_administrator_password_reset_also_revokes_existing_tokens(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        $tokenId = $this->issueToken($user);
        $this->actingAs($admin, 'api')->putJson('/api/user/'.$user->id, [
            'password' => 'Administrator-reset-123!',
        ])->assertOk();
        $this->assertDatabaseHas('oauth_access_tokens', ['id' => $tokenId, 'revoked' => true]);
        $this->assertDatabaseHas('oauth_refresh_tokens', ['access_token_id' => $tokenId, 'revoked' => true]);
    }

    public function test_recovery_password_reset_revokes_tokens_and_rejects_short_passwords(): void
    {
        $this->withoutMiddleware(CreateFreshApiToken::class);
        $user = User::factory()->create();
        $tokenId = $this->issueToken($user);
        $resetToken = Password::createToken($user);
        $this->post('/password/reset', [
            'email' => $user->email, 'token' => $resetToken,
            'password' => 'short', 'password_confirmation' => 'short',
        ])->assertSessionHasErrors('password');
        $this->assertDatabaseHas('oauth_access_tokens', ['id' => $tokenId, 'revoked' => false]);
        $this->post('/password/reset', [
            'email' => $user->email, 'token' => $resetToken,
            'password' => 'Recovered-password-123!', 'password_confirmation' => 'Recovered-password-123!',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('oauth_access_tokens', ['id' => $tokenId, 'revoked' => true]);
        $this->assertDatabaseHas('oauth_refresh_tokens', ['access_token_id' => $tokenId, 'revoked' => true]);
    }
}
