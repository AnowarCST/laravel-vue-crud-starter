<?php

namespace Tests\Feature\Auth;

use App\Enums\UserType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Passport\Http\Middleware\CreateFreshApiToken;
use Tests\TestCase;

class RegistrationTest extends TestCase
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
        $this->assertSame(UserType::User, $user->type);
        $this->assertTrue(Hash::check('Registration-password-123!', $user->password));
    }
}
