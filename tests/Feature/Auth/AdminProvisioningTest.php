<?php

namespace Tests\Feature\Auth;

use App\Enums\UserType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\PendingCommand;
use Tests\TestCase;

class AdminProvisioningTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_is_provisioned_with_interactive_password(): void
    {
        $this->createAdmin(['--email' => 'owner@example.com', '--name' => 'Owner'])
            ->expectsQuestion('Password (at least 12 characters)', 'Private-password-123!')
            ->expectsQuestion('Confirm password', 'Private-password-123!')
            ->expectsOutput('Administrator created.')
            ->assertSuccessful();
        $admin = User::where('email', 'owner@example.com')->firstOrFail();
        $this->assertSame(UserType::Admin, $admin->type);
        $this->assertTrue(Hash::check('Private-password-123!', $admin->password));
        $this->assertTrue($admin->hasVerifiedEmail(), 'Operator-provisioned accounts need no email verification.');
    }

    public function test_noninteractive_admin_provisioning_is_rejected(): void
    {
        $this->createAdmin(['--no-interaction' => true])
            ->assertFailed();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_weak_or_unconfirmed_administrator_password_is_rejected(): void
    {
        $this->createAdmin(['--email' => 'owner@example.com', '--name' => 'Owner'])
            ->expectsQuestion('Password (at least 12 characters)', 'short')
            ->expectsQuestion('Confirm password', 'different')
            ->assertFailed();
        $this->assertDatabaseCount('users', 0);
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    private function createAdmin(array $parameters): PendingCommand
    {
        $command = $this->artisan('app:create-admin', $parameters);
        $this->assertInstanceOf(PendingCommand::class, $command);

        return $command;
    }
}
