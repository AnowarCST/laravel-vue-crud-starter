<div class="{{ $class ?? 'login-logo' }}">
    <a href="{{ url('/') }}" class="d-inline-flex align-items-center gap-2">
        <img src="{{ asset('images/logo.svg') }}" alt="" class="auth-logo-image" width="48" height="48">
        <b>{{ config('app.name', 'Laravel') }}</b>
    </a>
</div>
