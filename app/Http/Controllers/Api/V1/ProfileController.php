<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Users\ChangePasswordRequest;
use App\Http\Requests\Users\ProfileUpdateRequest;
use App\Http\Resources\UserResource;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;

/**
 * Self-service endpoints. They only ever act on the authenticated account and
 * only accept name/email (profile) or a new password (change-password).
 */
class ProfileController extends Controller
{
    public function profile(#[CurrentUser] User $user): ApiResponse
    {
        return ApiResponse::success(new UserResource($user), 'User Profile');
    }

    public function updateProfile(ProfileUpdateRequest $request, #[CurrentUser] User $user): ApiResponse
    {
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

        return ApiResponse::success(new UserResource($user), 'Profile has been updated');
    }

    public function changePassword(ChangePasswordRequest $request, #[CurrentUser] User $user): ApiResponse
    {
        // The "hashed" cast hashes the value; the User model then rotates the
        // remember token and revokes all OAuth tokens for this account.
        $user->update(['password' => $request->validated('new_password')]);

        return ApiResponse::success([], 'Password Has been updated');
    }
}
