<?php

namespace Tests\Concerns;

use App\Models\User;

/**
 * Accounts for the tests, signed in on the "api" guard (bearer token or
 * SPA cookie). Every factory account has the password
 * UserFactory::PASSWORD.
 */
trait CreatesUsers
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function actingAsAdmin(array $attributes = []): User
    {
        $admin = User::factory()->admin()->create($attributes);
        $this->actingAs($admin, 'api');

        return $admin;
    }

    /**
     * A regular (non-administrator) account with a verified email address.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function actingAsUser(array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $this->actingAs($user, 'api');

        return $user;
    }

    /**
     * A regular account that has not verified its email address yet.
     */
    protected function actingAsUnverifiedUser(): User
    {
        $user = User::factory()->unverified()->create();
        $this->actingAs($user, 'api');

        return $user;
    }
}
