<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover, maximum-scale=1, user-scalable=no">
    <meta name="theme-color" content="#4361ee">
    <meta name="apple-mobile-web-app-capable" content="yes">
    @include('partials.theme-init')
    <title>Sign in — FLC</title>
    <link rel="stylesheet" href="{{ asset('css/user.css') }}?v={{ filemtime(public_path('css/user.css')) }}">
    <script defer src="{{ asset('js/user-app.js') }}?v={{ filemtime(public_path('js/user-app.js')) }}"></script>
</head>
<body class="user-body {{ ($isFlcApp ?? false) ? 'flc-app' : '' }}">
<div class="user-login-page">
    <div class="user-login-card">
        <div class="user-login-icon">🌐</div>
        <h1>FLC</h1>
        <p class="subtitle">Foreign Language Companion</p>

        @if (session('error'))
            <div class="alert alert-error">{{ session('error') }}</div>
        @endif
        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        <a href="{{ route('user.auth.google') }}" class="btn btn-block">
            Sign in with Google
        </a>

        <div class="theme-toggle" role="group" aria-label="Choose theme" style="margin-top:24px">
            <button type="button" data-theme-choice="light">☀️</button>
            <button type="button" data-theme-choice="dark">🌙</button>
            <button type="button" data-theme-choice="system">💻</button>
        </div>

        <p class="muted" style="margin-top:16px;font-size:12px">
            Your email must be on the allowlist. Contact an admin if you need access.
        </p>
    </div>
</div>
</body>
</html>
