<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_only_updates_authenticated_account_and_allowed_fields(): void
    {
        $user = User::factory()->create();
        $victim = User::factory()->create();
        $this->actingAs($user, 'api')->getJson('/api/profile')
            ->assertOk()->assertJsonPath('data.id', $user->id)->assertJsonMissingPath('data.password');
        $this->putJson('/api/profile', [
            'id' => $victim->id, 'name' => 'Updated', 'email' => $user->email,
            'type' => 'admin', 'password' => 'Injected-password-123!',
        ])->assertOk()->assertJsonPath('data.name', 'Updated');
        $this->assertSame('user', $user->refresh()->type);
        $this->assertTrue(Hash::check('Test-password-123!', $user->refresh()->password));
        $this->assertNotSame('Updated', $victim->refresh()->name);
    }

    public function test_profile_rejects_missing_fields_and_duplicate_email(): void
    {
        $user = User::factory()->create();
        $victim = User::factory()->create();
        $this->actingAs($user, 'api')->putJson('/api/profile', ['name' => 'Updated'])
            ->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->putJson('/api/profile', ['name' => 'Updated', 'email' => $victim->email])
            ->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    public function test_password_change_requires_current_password_and_strong_matching_replacement(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'api')->postJson('/api/change-password', [
            'current_password' => 'wrong-password', 'new_password' => 'New-password-123!',
            'confirm_password' => 'New-password-123!',
        ])->assertUnprocessable()->assertJsonValidationErrors('current_password');
        $this->postJson('/api/change-password', [
            'current_password' => 'Test-password-123!', 'new_password' => 'short', 'confirm_password' => 'short',
        ])->assertUnprocessable()->assertJsonValidationErrors('new_password');
        $this->postJson('/api/change-password', [
            'current_password' => 'Test-password-123!', 'new_password' => 'New-password-123!',
            'confirm_password' => 'New-password-123!',
        ])->assertOk();
        $this->assertTrue(Hash::check('New-password-123!', $user->refresh()->password));
        $this->assertFalse(Hash::check('Test-password-123!', $user->refresh()->password));
    }

    public function test_guests_cannot_access_profile_or_change_password(): void
    {
        $this->getJson('/api/profile')->assertUnauthorized();
        $this->putJson('/api/profile', [])->assertUnauthorized();
        $this->postJson('/api/change-password', [])->assertUnauthorized();
    }
}
