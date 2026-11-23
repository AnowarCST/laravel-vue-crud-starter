@extends('layouts.app')

@section('title', __('Register'))
@section('body_class', 'register-page')

@section('content')
<div class="register-box">
    @include('auth.partials.logo', ['class' => 'register-logo'])

    <div class="card card-outline card-primary">
        <div class="card-body register-card-body">
            <p class="register-box-msg">{{ __('Register a new membership') }}</p>

            <form method="POST" action="{{ route('register') }}">
                @csrf

                <div class="mb-3">
                    <label for="name" class="visually-hidden">{{ __('Name') }}</label>
                    <div class="input-group has-validation">
                        <input id="name" type="text" name="name" value="{{ old('name') }}" maxlength="191"
                               class="form-control @error('name') is-invalid @enderror"
                               placeholder="{{ __('Full name') }}" required autocomplete="name" autofocus>
                        <div class="input-group-text"><span class="fa-solid fa-user" aria-hidden="true"></span></div>
                        @error('name')
                            <div class="invalid-feedback" role="alert">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="mb-3">
                    <label for="email" class="visually-hidden">{{ __('E-Mail Address') }}</label>
                    <div class="input-group has-validation">
                        <input id="email" type="email" name="email" value="{{ old('email') }}" maxlength="191"
                               class="form-control @error('email') is-invalid @enderror"
                               placeholder="{{ __('E-Mail Address') }}" required autocomplete="email">
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
                               placeholder="{{ __('Password') }}" required autocomplete="new-password"
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
                               placeholder="{{ __('Retype password') }}" required autocomplete="new-password">
                        <div class="input-group-text"><span class="fa-solid fa-lock" aria-hidden="true"></span></div>
                    </div>
                </div>

                <div class="d-grid">
                    <button type="submit" class="btn btn-primary">{{ __('Register') }}</button>
                </div>
            </form>

            <hr>
            <p class="mb-0"><a href="{{ route('login') }}" class="text-center">{{ __('I already have a membership') }}</a></p>
        </div>
    </div>
</div>
@endsection
