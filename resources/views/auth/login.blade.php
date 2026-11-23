@extends('layouts.app')

@section('title', __('Login'))
@section('body_class', 'login-page')

@section('content')
<div class="login-box">
    @include('auth.partials.logo', ['class' => 'login-logo'])

    <div class="card card-outline card-primary">
        <div class="card-body login-card-body">
            <p class="login-box-msg">{{ __('Sign in to start your session') }}</p>

            @if (session('status'))
                <div class="alert alert-success" role="alert">{{ session('status') }}</div>
            @endif

            <form method="POST" action="{{ route('login') }}">
                @csrf

                <div class="mb-3">
                    <label for="email" class="visually-hidden">{{ __('E-Mail Address') }}</label>
                    <div class="input-group has-validation">
                        <input id="email" type="email" name="email" value="{{ old('email') }}"
                               class="form-control @error('email') is-invalid @enderror"
                               placeholder="{{ __('E-Mail Address') }}" required autocomplete="email" autofocus>
                        <div class="input-group-text"><span class="fa-solid fa-envelope" aria-hidden="true"></span></div>
                        @error('email')
                            <div class="invalid-feedback" role="alert">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="mb-3">
                    <label for="password" class="visually-hidden">{{ __('Password') }}</label>
                    <div class="input-group has-validation">
                        <input id="password" type="password" name="password"
                               class="form-control @error('password') is-invalid @enderror"
                               placeholder="{{ __('Password') }}" required autocomplete="current-password">
                        <div class="input-group-text"><span class="fa-solid fa-lock" aria-hidden="true"></span></div>
                        @error('password')
                            <div class="invalid-feedback" role="alert">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="row align-items-center">
                    <div class="col-7">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="remember" id="remember" {{ old('remember') ? 'checked' : '' }}>
                            <label class="form-check-label" for="remember">{{ __('Remember Me') }}</label>
                        </div>
                    </div>
                    <div class="col-5">
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">{{ __('Sign In') }}</button>
                        </div>
                    </div>
                </div>
            </form>

            <hr>
            @if (Route::has('password.request'))
                <p class="mb-1"><a href="{{ route('password.request') }}">{{ __('I forgot my password') }}</a></p>
            @endif
            @if (Route::has('register'))
                <p class="mb-0"><a href="{{ route('register') }}" class="text-center">{{ __('Register a new membership') }}</a></p>
            @endif
        </div>
    </div>
</div>
@endsection
