<?php

namespace Tests\Feature\Api\Users;

use App\Enums\UserType;
use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Administrator account management: /api/user.
 */
class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_manage_accounts_and_passwords_are_hashed(): void
    {
        $this->actingAsAdmin();
        $this->assertApiSuccess($this->getJson('/api/user'), 'Users list');
        $this->assertApiSuccess($this->postJson('/api/user', [
            'name' => 'New User', 'email' => 'new@example.com',
            'password' => 'Unique-password-123!', 'type' => 'user',
        ]), 'User Created Successfully');
        $user = User::where('email', 'new@example.com')->firstOrFail();
        $this->assertTrue(Hash::check('Unique-password-123!', $user->password));
        $this->assertApiSuccess($this->getJson('/api/user/'.$user->id), 'User Details')
            ->assertJsonMissingPath('data.password');
        $this->assertApiSuccess($this->putJson('/api/user/'.$user->id, ['name' => 'Updated', 'email' => $user->email]))
            ->assertJsonPath('data.name', 'Updated');
        $this->assertApiSuccess($this->patchJson('/api/user/'.$user->id, ['password' => 'Replacement-password-123!']),
            'User Information has been updated');
        $this->assertTrue(Hash::check('Replacement-password-123!', $user->refresh()->password));
        $this->assertApiSuccess($this->deleteJson('/api/user/'.$user->id), 'User has been Deleted');
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    public function test_non_admin_cannot_list_read_create_update_or_delete_accounts(): void
    {
        $user = $this->actingAsUser();
        $victim = User::factory()->admin()->create();
        $this->getJson('/api/user')->assertForbidden();
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
        $this->assertSame(UserType::User, $user->refresh()->type);
        $this->assertTrue(Hash::check(UserFactory::PASSWORD, $victim->refresh()->password));
        $this->assertDatabaseMissing('users', ['email' => 'attacker@example.com']);
    }

    public function test_duplicate_email_cannot_be_bypassed_using_body_id(): void
    {
        $admin = $this->actingAsAdmin();
        $victim = User::factory()->create();
        $this->putJson('/api/user/'.$admin->id, [
            'id' => $victim->id, 'email' => $victim->email,
        ])->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function invalidPasswords(): iterable
    {
        yield 'shorter than 12 characters' => ['short'];
        yield '30 characters but 120 bytes' => [str_repeat('🔐', 30)];
        yield 'longer than 72 characters' => [str_repeat('a', 73)];
        yield 'not a string' => [['Array-password-123!']];
    }

    #[DataProvider('invalidPasswords')]
    public function test_admin_updates_reject_invalid_passwords(mixed $password): void
    {
        $this->actingAsAdmin();
        $user = User::factory()->create();

        $this->putJson('/api/user/'.$user->id, ['password' => $password])
            ->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->assertTrue(Hash::check(UserFactory::PASSWORD, $user->refresh()->password));
    }

    public function test_admin_updates_ignore_unvalidated_fields(): void
    {
        $this->actingAsAdmin();
        $user = User::factory()->unverified()->create();
        $this->putJson('/api/user/'.$user->id, ['name' => 'Updated', 'email_verified_at' => now()->toISOString()])
            ->assertOk();
        $this->assertNull($user->refresh()->email_verified_at);
        $this->assertTrue(Hash::check(UserFactory::PASSWORD, $user->refresh()->password));
    }

    public function test_guests_cannot_manage_accounts(): void
    {
        $this->getJson('/api/user')->assertUnauthorized();
        $this->postJson('/api/user', [])->assertUnauthorized();
        $this->putJson('/api/user/1', ['type' => 'admin'])->assertUnauthorized();
        $this->deleteJson('/api/user/1')->assertUnauthorized();
    }
}
