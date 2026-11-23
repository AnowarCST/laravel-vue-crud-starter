<?php

namespace Database\Factories;

use App\Enums\UserType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Test-only factory. Never used by the database seeders, so no account with
 * this well-known password is ever created outside the test suite.
 *
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    public const PASSWORD = 'Test-password-123!';

    protected static ?string $passwordHash = null;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$passwordHash ??= Hash::make(self::PASSWORD),
            'remember_token' => Str::random(10),
            'type' => UserType::User,
        ];
    }

    public function admin(): static
    {
        return $this->type(UserType::Admin);
    }

    public function type(UserType $type): static
    {
        return $this->state(['type' => $type]);
    }

    public function unverified(): static
    {
        return $this->state(fn () => ['email_verified_at' => null]);
    }
}
