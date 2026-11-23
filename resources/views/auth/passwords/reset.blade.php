@extends('layouts.app')

@section('title', __('Reset Password'))
@section('body_class', 'login-page')

@section('content')
<div class="login-box">
    @include('auth.partials.logo', ['class' => 'login-logo'])

    <div class="card card-outline card-primary">
        <div class="card-body login-card-body">
            <p class="login-box-msg">{{ __('Choose a new password') }}</p>

            <form method="POST" action="{{ route('password.update') }}">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">

                <div class="mb-3">
                    <label for="email" class="visually-hidden">{{ __('E-Mail Address') }}</label>
                    <div class="input-group has-validation">
                        <input id="email" type="email" name="email" value="{{ $email ?? old('email') }}"
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
                        <input id="password" type="password" name="password" minlength="12" maxlength="72"
                               class="form-control @error('password') is-invalid @enderror"
                               placeholder="{{ __('New password') }}" required autocomplete="new-password"
                               aria-describedby="password-help">
                        <div class="input-group-text"><span class="fa-solid fa-lock" aria-hidden="true"></span></div>
                        @error('password')
                            <div class="invalid-feedback" role="alert">{{ $message }}</div>
                        @enderror
                    </div>
                    <div id="password-help" class="form-text">{{ __('At least 12 characters.') }}</div>
                </div>

                <div class="mb-3">
                    <label for="password-confirm" class="visually-hidden">{{ __('Confirm Password') }}</label>
                    <div class="input-group">
                        <input id="password-confirm" type="password" name="password_confirmation" class="form-control"
                               placeholder="{{ __('Confirm Password') }}" required autocomplete="new-password">
                        <div class="input-group-text"><span class="fa-solid fa-lock" aria-hidden="true"></span></div>
                    </div>
                </div>

                <div class="d-grid">
                    <button type="submit" class="btn btn-primary">{{ __('Reset Password') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
