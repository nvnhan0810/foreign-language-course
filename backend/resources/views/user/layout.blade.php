<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover, maximum-scale=1, user-scalable=no">
    <meta name="theme-color" content="#4361ee">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="format-detection" content="telephone=no">
    @include('partials.theme-init')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'FLC')</title>
    <link rel="stylesheet" href="{{ asset('css/user.css') }}?v={{ filemtime(public_path('css/user.css')) }}">
    <script defer src="{{ asset('js/user-app.js') }}?v={{ filemtime(public_path('js/user-app.js')) }}"></script>
</head>
<body class="user-body {{ ($isFlcApp ?? false) ? 'flc-app' : '' }}{{ View::hasSection('hide_nav') ? ' user-no-nav' : '' }}{{ View::hasSection('hide_header') ? ' user-no-header' : '' }}{{ View::hasSection('game_screen') ? ' user-game' : '' }}{{ View::hasSection('game_bg') ? ' user-game-bg' : '' }}">
<div class="user-shell">
    @unless (View::hasSection('hide_header'))
        @hasSection('header')
            @yield('header')
        @else
            <header class="user-header">
                @hasSection('back_url')
                    <a href="@yield('back_url')" class="user-header-back" aria-label="Back">←</a>
                @else
                    <span class="user-header-spacer" aria-hidden="true"></span>
                @endif
                <h1>@yield('heading', 'FLC')</h1>
                <span class="user-header-spacer" aria-hidden="true"></span>
            </header>
        @endif
    @endunless

    @hasSection('below_header')
        @yield('below_header')
    @endif

    <main class="user-main" @if(request()->routeIs('user.home.quiz.play') && request()->query('autostart') === '1') data-autostart-quiz="1" @endif>
        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="alert alert-error">{{ session('error') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-error">
                <ul style="margin:0;padding-left:18px">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @yield('content')
    </main>

    @unless (View::hasSection('hide_nav'))
        <nav class="user-nav" aria-label="Main navigation">
            <a href="{{ route('user.home.lookup') }}" class="{{ request()->routeIs('user.home.lookup') ? 'active' : '' }}">
                <span class="icon">📖</span>
                Lookup
            </a>
            <a href="{{ route('user.home.vocab') }}" class="{{ request()->routeIs('user.home.vocab*') ? 'active' : '' }}">
                <span class="icon">🔖</span>
                Vocabulary
            </a>
            <a href="{{ route('user.home.media') }}" class="{{ request()->routeIs('user.home.media*') ? 'active' : '' }}">
                <span class="icon">🎧</span>
                Listen
            </a>
            <a href="{{ route('user.home.quiz') }}" class="{{ request()->routeIs('user.home.quiz*') ? 'active' : '' }}">
                <span class="icon">🎮</span>
                Games
            </a>
            <a href="{{ route('user.home.profile') }}" class="{{ request()->routeIs('user.home.profile') ? 'active' : '' }}">
                <span class="icon">👤</span>
                Profile
            </a>
        </nav>
    @endunless
</div>
</body>
</html>
