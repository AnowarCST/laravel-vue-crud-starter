<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BaseController extends Controller
{
    /**
     * Wrap a successful result in the API's standard response envelope.
     */
    public function sendResponse(mixed $result, string $message): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $result,
            'message' => $message,
        ]);
    }

    /**
     * The account making the request. Every route that calls this is behind
     * the "auth:api" middleware, so a missing user is never expected here.
     */
    protected function authenticatedUser(Request $request): User
    {
        $user = $request->user();

        if (! $user instanceof User) {
            throw new AuthenticationException;
        }

        return $user;
    }
}
