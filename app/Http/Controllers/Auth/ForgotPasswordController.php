<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\SendsPasswordResetEmails;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;

class ForgotPasswordController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Password Reset Controller
    |--------------------------------------------------------------------------
    |
    | The response is identical whether or not the address belongs to an
    | account (or is currently throttled), so the form cannot be used to
    | enumerate registered email addresses.
    |
    */

    use SendsPasswordResetEmails;

    public const GENERIC_STATUS = 'If an account exists for that email address, a password reset link has been sent to it.';

    public function __construct()
    {
        $this->middleware('throttle:password-reset')->only('sendResetLinkEmail');
    }

    protected function sendResetLinkResponse(Request $request, $response)
    {
        return $request->wantsJson()
            ? new JsonResponse(['message' => __(self::GENERIC_STATUS)], 200)
            : back()->with('status', __(self::GENERIC_STATUS));
    }

    protected function sendResetLinkFailedResponse(Request $request, $response)
    {
        if (in_array($response, [Password::INVALID_USER, Password::RESET_THROTTLED], true)) {
            return $this->sendResetLinkResponse($request, $response);
        }

        return back()->withInput($request->only('email'))
            ->withErrors(['email' => __('Unable to send a password reset link. Please try again later.')]);
    }
}
