<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Users\CreateUser;
use App\Enums\UserType;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Auth\RegistersUsers;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class RegisterController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Register Controller
    |--------------------------------------------------------------------------
    |
    | Public self-registration always creates a regular "user" account.
    | Administrators are provisioned with `php artisan app:create-admin`.
    |
    */

    use RegistersUsers;

    protected string $redirectTo = '/dashboard';

    public function __construct()
    {
        $this->middleware('guest');
        $this->middleware('throttle:register')->only('register');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function validator(array $data): ValidatorContract
    {
        return Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', Password::defaults(), 'confirmed'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $data  Input that passed validator().
     */
    protected function create(array $data): User
    {
        // RegistersUsers then sends the verification email.
        return app(CreateUser::class)->handle(
            name: Arr::string($data, 'name'),
            email: Arr::string($data, 'email'),
            password: Arr::string($data, 'password'),
            type: UserType::User,
            markEmailAsVerified: false,
        );
    }
}
