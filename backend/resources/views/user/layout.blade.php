<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'FLC')</title>
    <link rel="stylesheet" href="{{ asset('css/user.css') }}?v={{ filemtime(public_path('css/user.css')) }}">
</head>
<body>
<div class="user-shell">
    @hasSection('header')
        @yield('header')
    @else
        <header class="user-header">
            <h1>@yield('heading', 'FLC')</h1>
        </header>
    @endif

    <main class="user-main">
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
        <nav class="user-nav">
            <a href="{{ route('user.home.lookup') }}" class="{{ request()->routeIs('user.home.lookup') ? 'active' : '' }}">
                <span class="icon">📖</span>
                Tra từ
            </a>
            <a href="{{ route('user.home.vocab') }}" class="{{ request()->routeIs('user.home.vocab') ? 'active' : '' }}">
                <span class="icon">🔖</span>
                Từ vựng
            </a>
            <a href="{{ route('user.home.media') }}" class="{{ request()->routeIs('user.home.media*') ? 'active' : '' }}">
                <span class="icon">🎧</span>
                Nghe
            </a>
            <a href="{{ route('user.home.quiz') }}" class="{{ request()->routeIs('user.home.quiz') ? 'active' : '' }}">
                <span class="icon">❓</span>
                Quiz
            </a>
            <a href="{{ route('user.home.profile') }}" class="{{ request()->routeIs('user.home.profile') ? 'active' : '' }}">
                <span class="icon">👤</span>
                Cá nhân
            </a>
        </nav>
    @endunless
</div>
</body>
</html>
