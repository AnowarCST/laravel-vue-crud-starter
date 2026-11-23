@extends('layouts.app')

@section('title', __('Confirm Password'))
@section('body_class', 'login-page')

@section('content')
<div class="login-box">
    @include('auth.partials.logo', ['class' => 'login-logo'])

    <div class="card card-outline card-primary">
        <div class="card-body login-card-body">
            <p class="login-box-msg">{{ __('Please confirm your password before continuing.') }}</p>

            <form method="POST" action="{{ route('password.confirm') }}">
                @csrf

                <div class="mb-3">
                    <label for="password" class="visually-hidden">{{ __('Password') }}</label>
                    <div class="input-group has-validation">
                        <input id="password" type="password" name="password"
                               class="form-control @error('password') is-invalid @enderror"
                               placeholder="{{ __('Password') }}" required autocomplete="current-password" autofocus>
                        <div class="input-group-text"><span class="fa-solid fa-lock" aria-hidden="true"></span></div>
                        @error('password')
                            <div class="invalid-feedback" role="alert">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="d-grid">
                    <button type="submit" class="btn btn-primary">{{ __('Confirm Password') }}</button>
                </div>
            </form>

            @if (Route::has('password.request'))
                <hr>
                <p class="mb-0"><a href="{{ route('password.request') }}">{{ __('Forgot Your Password?') }}</a></p>
            @endif
        </div>
    </div>
</div>
@endsection
