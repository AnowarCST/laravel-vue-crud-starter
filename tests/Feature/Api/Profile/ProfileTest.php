<?php

namespace Tests\Feature\Api\Profile;

use App\Enums\UserType;
use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Self-service: GET/PUT /api/profile and POST /api/change-password. The
 * email verification side of a profile change is in
 * Tests\Feature\Auth\EmailVerificationTest.
 */
class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_only_updates_authenticated_account_and_allowed_fields(): void
    {
        $user = $this->actingAsUser();
        $victim = User::factory()->create();
        $this->assertApiSuccess($this->getJson('/api/profile'), 'User Profile')
            ->assertJsonPath('data.id', $user->id)->assertJsonMissingPath('data.password');
        $this->assertApiSuccess($this->putJson('/api/profile', [
            'id' => $victim->id, 'name' => 'Updated', 'email' => $user->email,
            'type' => 'admin', 'password' => 'Injected-password-123!',
        ]), 'Profile has been updated')->assertJsonPath('data.name', 'Updated');
        $this->assertSame(UserType::User, $user->refresh()->type);
        $this->assertTrue(Hash::check(UserFactory::PASSWORD, $user->refresh()->password));
        $this->assertNotSame('Updated', $victim->refresh()->name);
    }

    public function test_profile_rejects_missing_fields_and_duplicate_email(): void
    {
        $this->actingAsUser();
        $victim = User::factory()->create();
        $this->putJson('/api/profile', ['name' => 'Updated'])
            ->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->putJson('/api/profile', ['name' => 'Updated', 'email' => $victim->email])
            ->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function invalidPasswordChanges(): iterable
    {
        $new = 'New-password-123!';

        yield 'wrong current password' => [['current_password' => 'wrong-password', 'new_password' => $new, 'confirm_password' => $new], 'current_password'];
        yield 'new password too short' => [['current_password' => UserFactory::PASSWORD, 'new_password' => 'short', 'confirm_password' => 'short'], 'new_password'];
        yield 'new password over 72 bytes' => [['current_password' => UserFactory::PASSWORD, 'new_password' => str_repeat('🔐', 30), 'confirm_password' => str_repeat('🔐', 30)], 'new_password'];
        yield 'new password same as current' => [['current_password' => UserFactory::PASSWORD, 'new_password' => UserFactory::PASSWORD, 'confirm_password' => UserFactory::PASSWORD], 'new_password'];
        yield 'confirmation does not match' => [['current_password' => UserFactory::PASSWORD, 'new_password' => $new, 'confirm_password' => 'Other-password-123!'], 'confirm_password'];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    #[DataProvider('invalidPasswordChanges')]
    public function test_password_change_is_validated(array $payload, string $invalidField): void
    {
        $user = $this->actingAsUser();

        $this->postJson('/api/change-password', $payload)
            ->assertUnprocessable()->assertJsonValidationErrors($invalidField);
        $this->assertTrue(Hash::check(UserFactory::PASSWORD, $user->refresh()->password));
    }

    public function test_password_change_with_the_current_password_and_a_strong_replacement(): void
    {
        $user = $this->actingAsUser();

        $this->assertApiSuccess($this->postJson('/api/change-password', [
            'current_password' => UserFactory::PASSWORD, 'new_password' => 'New-password-123!',
            'confirm_password' => 'New-password-123!',
        ]), 'Password Has been updated')->assertJsonPath('data', []);
        $this->assertTrue(Hash::check('New-password-123!', $user->refresh()->password));
        $this->assertFalse(Hash::check(UserFactory::PASSWORD, $user->refresh()->password));
    }

    public function test_guests_cannot_access_profile_or_change_password(): void
    {
        $this->getJson('/api/profile')->assertUnauthorized();
        $this->putJson('/api/profile', [])->assertUnauthorized();
        $this->postJson('/api/change-password', [])->assertUnauthorized();
    }
}
