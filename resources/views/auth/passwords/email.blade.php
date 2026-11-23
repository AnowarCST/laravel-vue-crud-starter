@extends('layouts.app')

@section('title', __('Reset Password'))
@section('body_class', 'login-page')

@section('content')
<div class="login-box">
    @include('auth.partials.logo', ['class' => 'login-logo'])

    <div class="card card-outline card-primary">
        <div class="card-body login-card-body">
            <p class="login-box-msg">{{ __('Forgot your password? Enter your email and we will send you a reset link.') }}</p>

            @if (session('status'))
                <div class="alert alert-success" role="alert">{{ session('status') }}</div>
            @endif

            <form method="POST" action="{{ route('password.email') }}">
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

                <div class="d-grid">
                    <button type="submit" class="btn btn-primary">{{ __('Send Password Reset Link') }}</button>
                </div>
            </form>

            <hr>
            <p class="mb-0"><a href="{{ route('login') }}">{{ __('Back to login') }}</a></p>
        </div>
    </div>
</div>
@endsection
