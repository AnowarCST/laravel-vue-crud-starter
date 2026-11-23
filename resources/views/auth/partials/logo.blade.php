<div class="{{ $class ?? 'login-logo' }}">
    <a href="{{ url('/') }}" class="d-inline-flex align-items-center gap-2">
        <img src="{{ asset('images/logo.png') }}" alt="" class="auth-logo-image">
        <b>{{ config('app.name', 'Laravel') }}</b>
    </a>
</div>
