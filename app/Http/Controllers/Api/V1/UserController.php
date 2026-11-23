<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Users\CreateUser;
use App\Enums\UserType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Users\UserRequest;
use App\Http\Resources\UserResource;
use App\Http\Responses\ApiResponse;
use App\Models\User;

/**
 * Administrator-only account management (see App\Policies\UserPolicy).
 * Authorization is always checked before an account is looked up so that
 * non-administrators cannot probe which user ids exist. That is why these
 * actions take the id instead of using route model binding, which would
 * look the account up (and answer 404) before any policy check.
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

    public function store(UserRequest $request, CreateUser $createUser): ApiResponse
    {
        $validated = $request->safe();

        // The administrator chose this address, so the account does not have
        // to go through the self-registration email verification step.
        $user = $createUser->handle(
            name: $validated->string('name')->value(),
            email: $validated->string('email')->value(),
            password: $validated->string('password')->value(),
            type: $validated->enum('type', UserType::class) ?? UserType::User,
            markEmailAsVerified: true,
        );

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
        $user->forceFill($request->accountChanges())->save();

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
