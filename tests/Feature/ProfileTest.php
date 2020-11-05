<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Faker\Generator as Faker;

class ProfileTest extends TestCase
{
    use WithFaker;

    protected function setUp(): void
    {
        parent::setUp();
    }

    /**
     * Test Auth user profile data
     *
     * @return void
     */
    public function testProfileData()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'api')
            ->getJson('/api/profile');

        $response
            ->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => true,
            ]);
    }

    /**
     * Test profile data without Auth
     *
     * @return void
     */
    public function testProfileDataWithoutAuth()
    {
        $user = User::factory()->create();

        $response = $this->getJson('/api/profile');

        $response->assertStatus(401);
    }

    /**
     * Test Update Profile Success
     *
     * @return void
     */
    public function testUpdateProfile()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'api')
            ->putJson('/api/profile', ['email' => $this->faker->email, 'name' => $this->faker->name]);

        $response
            ->assertStatus(200)
            ->assertJson([
                'success' => true
            ]);
    }

    /**
     * Test Update Profile Validation Error
     *
     * @return void
     */
    public function testUpdateProfileValidationError()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'api')
            ->putJson('/api/profile', ['name' => $this->faker->name]);

        $response->assertStatus(422);
    }

    /**
     * Test Change Password Success
     *
     * @return void
     */
    public function testChangePassword()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'api')
            ->postJson('/api/change-password', [
                'current_password' => 123456,
                'new_password' => 123456789,
                'confirm_password' => 123456789
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => true
            ]);
    }


    /**
     * Test Change Password Validation Error
     *
     * @return void
     */
    public function testChangePasswordValidationError()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'api')
            ->postJson('/api/change-password', ['current_password' => $this->faker->password]);

        $response->assertStatus(422);
    }
}
