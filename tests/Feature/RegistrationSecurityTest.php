<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Passport\Http\Middleware\CreateFreshApiToken;
use Tests\TestCase;

class RegistrationSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_registration_never_accepts_an_admin_role(): void
    {
        $this->withoutMiddleware(CreateFreshApiToken::class);
        $this->post('/register', [
            'name' => 'Public User', 'email' => 'public@example.com',
            'password' => 'Registration-password-123!',
            'password_confirmation' => 'Registration-password-123!', 'type' => 'admin',
        ])->assertRedirect();
        $user = User::where('email', 'public@example.com')->firstOrFail();
        $this->assertSame('user', $user->type);
        $this->assertTrue(Hash::check('Registration-password-123!', $user->password));
    }

    public function test_database_seeding_does_not_provision_a_known_admin(): void
    {
        $this->seed(\Database\Seeders\UsersTableSeeder::class);
        $this->assertDatabaseCount('users', 0);
    }
}
