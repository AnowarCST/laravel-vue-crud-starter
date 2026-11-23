<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Profile\ChangePassword;
use App\Actions\Profile\UpdateProfile;
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

    public function updateProfile(ProfileUpdateRequest $request, #[CurrentUser] User $user, UpdateProfile $updateProfile): ApiResponse
    {
        $validated = $request->safe();
        $updateProfile->handle($user, $validated->string('name')->value(), $validated->string('email')->value());

        return ApiResponse::success(new UserResource($user), 'Profile has been updated');
    }

    public function changePassword(ChangePasswordRequest $request, #[CurrentUser] User $user, ChangePassword $changePassword): ApiResponse
    {
        $changePassword->handle($user, $request->safe()->string('new_password')->value());

        return ApiResponse::success([], 'Password Has been updated');
    }
}
