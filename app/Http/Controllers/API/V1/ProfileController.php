<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Requests\Users\ChangePasswordRequest;
use App\Http\Requests\Users\ProfileUpdateRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Self-service endpoints. They only ever act on the authenticated account and
 * only accept name/email (profile) or a new password (change-password).
 */
class ProfileController extends BaseController
{
    public function profile(Request $request): JsonResponse
    {
        return $this->sendResponse($this->authenticatedUser($request), 'User Profile');
    }

    public function updateProfile(ProfileUpdateRequest $request): JsonResponse
    {
        $user = $this->authenticatedUser($request);
        $user->fill($request->safe()->only(['name', 'email']));

        // A new address must be verified again before the account can keep
        // using the SPA and the API.
        $emailChanged = $user->isDirty('email');
        if ($emailChanged) {
            $user->forceFill(['email_verified_at' => null]);
        }

        $user->save();

        if ($emailChanged) {
            $user->sendEmailVerificationNotification();
        }

        return $this->sendResponse($user, 'Profile has been updated');
    }

    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        // The "hashed" cast hashes the value; the User model then rotates the
        // remember token and revokes all OAuth tokens for this account.
        $this->authenticatedUser($request)->update(['password' => $request->validated('new_password')]);

        return $this->sendResponse([], 'Password Has been updated');
    }
}
