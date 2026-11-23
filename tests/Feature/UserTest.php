<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_manage_accounts_and_passwords_are_hashed(): void
    {
        $admin = User::factory()->create(['type' => 'admin']);
        $this->actingAs($admin, 'api')->getJson('/api/user')->assertOk()->assertJsonPath('success', true);
        $this->postJson('/api/user', [
            'name' => 'New User', 'email' => 'new@example.com',
            'password' => 'Unique-password-123!', 'type' => 'user',
        ])->assertOk();
        $user = User::where('email', 'new@example.com')->firstOrFail();
        $this->assertTrue(Hash::check('Unique-password-123!', $user->password));
        $this->getJson('/api/user/'.$user->id)->assertOk()->assertJsonMissingPath('data.password');
        $this->putJson('/api/user/'.$user->id, ['name' => 'Updated', 'email' => $user->email])
            ->assertOk()->assertJsonPath('data.name', 'Updated');
        $this->patchJson('/api/user/'.$user->id, ['password' => 'Replacement-password-123!'])
            ->assertOk();
        $this->assertTrue(Hash::check('Replacement-password-123!', $user->fresh()->password));
        $this->deleteJson('/api/user/'.$user->id)->assertOk();
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    public function test_non_admin_cannot_list_read_create_update_or_delete_accounts(): void
    {
        $user = User::factory()->create();
        $victim = User::factory()->create(['type' => 'admin']);
        $this->actingAs($user, 'api')->getJson('/api/user')->assertForbidden();
        $this->getJson('/api/user/'.$victim->id)->assertForbidden();
        $this->postJson('/api/user', [
            'name' => 'Attacker', 'email' => 'attacker@example.com',
            'password' => 'Attacker-password-123!', 'type' => 'admin',
        ])->assertForbidden();
        $this->putJson('/api/user/'.$victim->id, ['password' => 'Attacker-password-123!'])
            ->assertForbidden();
        $this->patchJson('/api/user/'.$victim->id, ['email' => 'attacker@example.com'])
            ->assertForbidden();
        $this->putJson('/api/user/'.$user->id, ['type' => 'admin'])->assertForbidden();
        $this->deleteJson('/api/user/'.$victim->id)->assertForbidden();
        $this->assertSame('user', $user->fresh()->type);
        $this->assertTrue(Hash::check('Test-password-123!', $victim->fresh()->password));
        $this->assertDatabaseMissing('users', ['email' => 'attacker@example.com']);
    }

    public function test_duplicate_email_cannot_be_bypassed_using_body_id(): void
    {
        $admin = User::factory()->create(['type' => 'admin']);
        $victim = User::factory()->create();
        $this->actingAs($admin, 'api')->putJson('/api/user/'.$admin->id, [
            'id' => $victim->id, 'email' => $victim->email,
        ])->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    public function test_admin_updates_validate_passwords_and_ignore_unvalidated_fields(): void
    {
        $admin = User::factory()->create(['type' => 'admin']);
        $user = User::factory()->create(['email_verified_at' => null]);
        $this->actingAs($admin, 'api')->putJson('/api/user/'.$user->id, ['password' => 'short'])
            ->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->putJson('/api/user/'.$user->id, ['password' => str_repeat('🔐', 30)])
            ->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->putJson('/api/user/'.$user->id, ['name' => 'Updated', 'email_verified_at' => now()->toISOString()])
            ->assertOk();
        $this->assertNull($user->fresh()->email_verified_at);
        $this->assertTrue(Hash::check('Test-password-123!', $user->fresh()->password));
    }

    public function test_guests_cannot_manage_accounts(): void
    {
        $this->getJson('/api/user')->assertUnauthorized();
        $this->postJson('/api/user', [])->assertUnauthorized();
        $this->putJson('/api/user/1', ['type' => 'admin'])->assertUnauthorized();
        $this->deleteJson('/api/user/1')->assertUnauthorized();
    }
}
