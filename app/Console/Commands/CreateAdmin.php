<?php

namespace App\Console\Commands;

use App\Actions\Users\CreateUser;
use App\Enums\UserType;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class CreateAdmin extends Command
{
    protected $signature = 'app:create-admin {--email=} {--name=}';

    protected $description = 'Create an administrator with an interactively entered password';

    public function handle(CreateUser $createUser): int
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
            'password' => ['required', 'string', Password::defaults(), 'confirmed'],
        ]);
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $validated = $validator->safe();
        // Provisioned by an operator, so no email verification step is needed.
        $createUser->handle(
            name: $validated->string('name')->value(),
            email: $validated->string('email')->value(),
            password: $validated->string('password')->value(),
            type: UserType::Admin,
            markEmailAsVerified: true,
        );
        $this->info('Administrator created.');

        return self::SUCCESS;
    }
}
