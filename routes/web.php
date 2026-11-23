<?php

use App\Http\Controllers\ApiDocsController;
use App\Http\Middleware\AuthorizeApiDocs;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome');

// /login, /logout, /register, /password/* and /email/* (verification).
Auth::routes(['verify' => true]);

// Post-login landing URL used by laravel/ui; the SPA lives at /dashboard.
Route::get('/home', fn () => redirect('/dashboard'))->name('home');

// OpenAPI document (docs/api/openapi.yaml) and its viewer. Who may open them
// depends on API_DOCS_ENABLED and the environment, see config/api-docs.php.
Route::middleware(AuthorizeApiDocs::class)->group(function () {
    Route::get('/docs', [ApiDocsController::class, 'show'])->name('api-docs');
    Route::get('/docs/openapi.yaml', [ApiDocsController::class, 'spec'])->name('api-docs.spec');
});

// Vue SPA catch-all (must stay last). API/OAuth paths are excluded so unknown
// endpoints there return JSON 404s instead of the SPA shell / login redirect.
// Unverified accounts are redirected to /email/verify.
Route::view('/{vue_capture?}', 'home')
    ->where('vue_capture', '(?!(?:api|oauth)(?:/|$))[\/\w\.-]*')
    ->middleware(['auth', 'verified']);
