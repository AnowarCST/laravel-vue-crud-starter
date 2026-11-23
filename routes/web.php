<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome');

// /login, /logout, /register, /password/* and /email/* (verification).
Auth::routes(['verify' => true]);

// Post-login landing URL used by laravel/ui; the SPA lives at /dashboard.
Route::get('/home', fn () => redirect('/dashboard'))->name('home');

// Vue SPA catch-all (must stay last). API/OAuth paths are excluded so unknown
// endpoints there return JSON 404s instead of the SPA shell / login redirect.
// Unverified accounts are redirected to /email/verify.
Route::view('/{vue_capture?}', 'home')
    ->where('vue_capture', '(?!(?:api|oauth)(?:/|$))[\/\w\.-]*')
    ->middleware(['auth', 'verified']);
