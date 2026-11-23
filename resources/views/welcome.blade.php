<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <title>{{ config('app.name', 'Laravel') }}</title>
    @vite('resources/js/auth.js')
</head>
<body class="bg-body-tertiary">
    <nav class="navbar navbar-expand bg-body border-bottom">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center gap-2" href="{{ url('/') }}">
                <img src="{{ asset('images/logo.png') }}" alt="" class="auth-logo-image">
                <span>{{ config('app.name', 'Laravel') }}</span>
            </a>
            @if (Route::has('login'))
                <ul class="navbar-nav ms-auto">
                    @auth
                        <li class="nav-item"><a class="nav-link" href="{{ url('/dashboard') }}">{{ __('Dashboard') }}</a></li>
                    @else
                        <li class="nav-item"><a class="nav-link" href="{{ route('login') }}">{{ __('Login') }}</a></li>
                        @if (Route::has('register'))
                            <li class="nav-item"><a class="nav-link" href="{{ route('register') }}">{{ __('Register') }}</a></li>
                        @endif
                    @endauth
                </ul>
            @endif
        </div>
    </nav>

    <main class="container py-5">
        <div class="row align-items-center g-5 py-lg-5">
            <div class="col-lg-7">
                <span class="badge text-bg-primary mb-3">Laravel + Vue Starter Kit</span>
                <h1 class="display-5 fw-bold mb-3">Ship CRUD apps faster with a modern developer experience.</h1>
                <p class="lead text-secondary mb-4">
                    Manage users, products, categories and tags from an AdminLTE dashboard backed by a
                    Laravel REST API, with a Docker-first toolchain.
                </p>
                <div class="d-flex flex-wrap gap-2">
                    <a href="{{ route('login') }}" class="btn btn-primary btn-lg">{{ __('Explore the dashboard') }}</a>
                    @if (Route::has('register'))
                        <a href="{{ route('register') }}" class="btn btn-outline-secondary btn-lg">{{ __('Create an account') }}</a>
                    @endif
                </div>
            </div>
            <div class="col-lg-5">
                <div class="row g-3">
                    <div class="col-12">
                        <div class="card h-100">
                            <div class="card-body">
                                <h2 class="h5"><i class="fa-solid fa-code text-primary me-2" aria-hidden="true"></i>Clean API surface</h2>
                                <p class="mb-0 text-secondary">REST endpoints, OAuth2 via Laravel Passport and request validation, ready to extend.</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="card h-100">
                            <div class="card-body">
                                <h2 class="h5"><i class="fa-solid fa-layer-group text-success me-2" aria-hidden="true"></i>Vue 3 single-page UI</h2>
                                <p class="mb-0 text-secondary">Reusable components on AdminLTE 4 and Bootstrap 5, built with Vite.</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="card h-100">
                            <div class="card-body">
                                <h2 class="h5"><i class="fa-solid fa-box-open text-warning me-2" aria-hidden="true"></i>Docker workflow</h2>
                                <p class="mb-0 text-secondary">Spin up the full stack locally with one command on macOS, Windows or Linux.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
