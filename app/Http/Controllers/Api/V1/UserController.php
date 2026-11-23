<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Users\UserRequest;
use App\Http\Resources\UserResource;
use App\Http\Responses\ApiResponse;
use App\Models\User;

/**
 * Administrator-only account management (see App\Policies\UserPolicy).
 * Authorization is always checked before an account is looked up so that
 * non-administrators cannot probe which user ids exist.
 */
class UserController extends Controller
{
    public function index(): ApiResponse
    {
        $this->authorize('viewAny', User::class);

        $users = User::latest()->paginate(10)
            ->through(fn (User $user) => new UserResource($user));

        return ApiResponse::success($users, 'Users list');
    }

    public function store(UserRequest $request): ApiResponse
    {
        $data = $request->validated();

        // The administrator chose this address, so the account does not have
        // to go through the self-registration email verification step.
        $user = new User($request->safe()->only(['name', 'email', 'password']));
        $user->forceFill(['type' => $data['type'], 'email_verified_at' => now()])->save();

        return ApiResponse::success(new UserResource($user), 'User Created Successfully');
    }

    public function show(string $id): ApiResponse
    {
        $this->authorize('viewAny', User::class);
        $user = User::findOrFail($id);
        $this->authorize('view', $user);

        return ApiResponse::success(new UserResource($user), 'User Details');
    }

    public function update(UserRequest $request, string $id): ApiResponse
    {
        $user = $request->target();

        $attributes = $request->safe()->only(['name', 'email']);
        if (filled($request->validated('password'))) {
            $attributes['password'] = $request->validated('password');
        }
        if ($request->has('type')) {
            $attributes['type'] = $request->validated('type');
        }

        $user->forceFill($attributes)->save();

        return ApiResponse::success(new UserResource($user), 'User Information has been updated');
    }

    public function destroy(string $id): ApiResponse
    {
        $this->authorize('viewAny', User::class);
        $user = User::findOrFail($id);
        $this->authorize('delete', $user);

        $user->delete();

        return ApiResponse::success([new UserResource($user)], 'User has been Deleted');
    }
}
