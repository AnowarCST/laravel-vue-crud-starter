<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Rules\PasswordByteLength;
use Illuminate\Foundation\Auth\ResetsPasswords;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ResetPasswordController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Password Reset Controller
    |--------------------------------------------------------------------------
    |
    | Saving the new password through the User model rotates the remember
    | token and revokes every OAuth token issued to the account.
    |
    */

    use ResetsPasswords;

    protected string $redirectTo = '/dashboard';

    public function __construct()
    {
        $this->middleware('throttle:password-reset')->only('reset');
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:12', 'max:72', 'confirmed', new PasswordByteLength],
        ];
    }

    /**
     * Same message for unknown emails and invalid/expired tokens.
     */
    protected function sendResetFailedResponse(Request $request, string $response): RedirectResponse
    {
        $message = __('This password reset link is invalid or has expired.');

        if ($request->wantsJson()) {
            throw ValidationException::withMessages(['email' => [$message]]);
        }

        return redirect()->back()
            ->withInput($request->only('email'))
            ->withErrors(['email' => $message]);
    }
}
