<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Rules\PasswordByteLength;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

class CreateAdmin extends Command
{
    protected $signature = 'app:create-admin {--email=} {--name=}';

    protected $description = 'Create an administrator with an interactively entered password';

    public function handle(): int
    {
        if (! $this->input->isInteractive()) {
            $this->error('Administrator provisioning requires an interactive terminal. Passwords are never accepted as command arguments.');

            return self::FAILURE;
        }

        $data = [
            'email' => $this->option('email') ?: $this->ask('Email address'),
            'name' => $this->option('name') ?: $this->ask('Name'),
            'password' => $this->secret('Password (at least 12 characters)'),
            'password_confirmation' => $this->secret('Confirm password'),
        ];
        $validator = Validator::make($data, [
            'email' => ['required', 'string', 'email', 'max:191', 'unique:users,email'],
            'name' => ['required', 'string', 'max:191'],
            'password' => ['required', 'string', 'min:12', 'max:72', 'confirmed', new PasswordByteLength],
        ]);
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $admin = new User([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'], // hashed by the model cast
        ]);
        // Provisioned by an operator, so no email verification step is needed.
        $admin->forceFill(['type' => User::TYPE_ADMIN, 'email_verified_at' => now()])->save();
        $this->info('Administrator created.');

        return self::SUCCESS;
    }
}
