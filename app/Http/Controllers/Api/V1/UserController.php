<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Users\UserRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;

/**
 * Administrator-only account management (see App\Policies\UserPolicy).
 * Authorization is always checked before an account is looked up so that
 * non-administrators cannot probe which user ids exist.
 */
class UserController extends BaseController
{
    public function index(): JsonResponse
    {
        $this->authorize('viewAny', User::class);

        return $this->sendResponse(User::latest()->paginate(10), 'Users list');
    }

    public function store(UserRequest $request): JsonResponse
    {
        $data = $request->validated();

        // The administrator chose this address, so the account does not have
        // to go through the self-registration email verification step.
        $user = new User($request->safe()->only(['name', 'email', 'password']));
        $user->forceFill(['type' => $data['type'], 'email_verified_at' => now()])->save();

        return $this->sendResponse($user, 'User Created Successfully');
    }

    public function show(string $id): JsonResponse
    {
        $this->authorize('viewAny', User::class);
        $user = User::findOrFail($id);
        $this->authorize('view', $user);

        return $this->sendResponse($user, 'User Details');
    }

    public function update(UserRequest $request, string $id): JsonResponse
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

        return $this->sendResponse($user, 'User Information has been updated');
    }

    public function destroy(string $id): JsonResponse
    {
        $this->authorize('viewAny', User::class);
        $user = User::findOrFail($id);
        $this->authorize('delete', $user);

        $user->delete();

        return $this->sendResponse([$user], 'User has been Deleted');
    }
}
