<?php

use App\Http\Controllers\API\V1\CategoryController;
use App\Http\Controllers\API\V1\ProductController;
use App\Http\Controllers\API\V1\ProfileController;
use App\Http\Controllers\API\V1\TagController;
use App\Http\Controllers\API\V1\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes (prefix /api, "api" middleware group incl. throttle:api)
|--------------------------------------------------------------------------
|
| Every route except "version" requires the Passport "api" guard, which
| accepts bearer tokens and the SPA's laravel_token cookie + X-CSRF-TOKEN,
| and a verified email address: unverified accounts receive 403 with
| {"message": "Your email address is not verified."}.
|
*/

Route::get('version', fn () => response()->json(['version' => config('app.version')]));

Route::middleware(['auth:api', 'verified'])->group(function () {
    Route::get('profile', [ProfileController::class, 'profile']);
    Route::put('profile', [ProfileController::class, 'updateProfile']);
    Route::post('change-password', [ProfileController::class, 'changePassword']);
    Route::get('tag/list', [TagController::class, 'list']);
    Route::get('category/list', [CategoryController::class, 'list']);
    Route::post('product/upload', [ProductController::class, 'upload']);
    Route::get('product/stats', [ProductController::class, 'stats']);

    Route::apiResources([
        'user' => UserController::class,
        'product' => ProductController::class,
        'category' => CategoryController::class,
        'tag' => TagController::class,
    ]);
});
