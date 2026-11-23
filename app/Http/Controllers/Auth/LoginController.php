<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\AuthenticatesUsers;

class LoginController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Login Controller
    |--------------------------------------------------------------------------
    |
    | AuthenticatesUsers (ThrottlesLogins) locks out an email + IP pair after
    | $maxAttempts failures for $decayMinutes; the "login" rate limiter adds a
    | per-IP cap on submissions across all emails.
    |
    */

    use AuthenticatesUsers;

    protected $redirectTo = '/dashboard';

    protected $maxAttempts = 5;

    protected $decayMinutes = 1;

    public function __construct()
    {
        $this->middleware('guest')->except('logout');
        $this->middleware('auth')->only('logout');
        $this->middleware('throttle:login')->only('login');
    }
}
