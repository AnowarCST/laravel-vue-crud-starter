<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Requests\Users\ChangePasswordRequest;
use App\Http\Requests\Users\ProfileUpdateRequest;
use Illuminate\Http\Request;

/**
 * Self-service endpoints. They only ever act on the authenticated account and
 * only accept name/email (profile) or a new password (change-password).
 */
class ProfileController extends BaseController
{
    public function profile(Request $request)
    {
        return $this->sendResponse($request->user(), 'User Profile');
    }

    public function updateProfile(ProfileUpdateRequest $request)
    {
        $user = $request->user();
        $user->update($request->validated());

        return $this->sendResponse($user, 'Profile has been updated');
    }

    public function changePassword(ChangePasswordRequest $request)
    {
        // The "hashed" cast hashes the value; the User model then rotates the
        // remember token and revokes all OAuth tokens for this account.
        $request->user()->update(['password' => $request->validated('new_password')]);

        return $this->sendResponse([], 'Password Has been updated');
    }
}
