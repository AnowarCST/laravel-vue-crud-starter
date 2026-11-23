@extends('layouts.app')

@section('title', __('Verify Your Email Address'))
@section('body_class', 'login-page')

@section('content')
<div class="login-box">
    @include('auth.partials.logo', ['class' => 'login-logo'])

    <div class="card card-outline card-primary">
        <div class="card-body login-card-body">
            <p class="login-box-msg">{{ __('Verify Your Email Address') }}</p>

            @if (session('resent'))
                <div class="alert alert-success" role="alert">
                    {{ __('A fresh verification link has been sent to your email address.') }}
                </div>
            @endif

            <p>{{ __('Before proceeding, please check your email for a verification link.') }}</p>
            <p>{{ __('If you did not receive the email') }}:</p>

            <form method="POST" action="{{ route('verification.resend') }}" class="d-grid mb-3">
                @csrf
                <button type="submit" class="btn btn-primary">{{ __('Request another verification link') }}</button>
            </form>

            <form method="POST" action="{{ route('logout') }}" class="text-center">
                @csrf
                <button type="submit" class="btn btn-link p-0">{{ __('Logout') }}</button>
            </form>
        </div>
    </div>
</div>
@endsection
