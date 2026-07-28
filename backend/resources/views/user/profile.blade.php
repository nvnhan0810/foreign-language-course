@extends('user.layout')

@section('title', 'Profile — FLC')

@section('header')
    <div class="profile-header">
        <div class="profile-avatar">
            {{ strtoupper(substr($user->name ?: $user->email, 0, 1)) }}
        </div>
        <h2 style="margin:0;font-size:20px">{{ $user->name ?: 'User' }}</h2>
        <p style="margin:4px 0 0;opacity:0.8;font-size:14px">{{ $user->email }}</p>
        <form action="{{ route('user.logout') }}" method="POST" style="margin-top:16px" class="flc-form-submit">
            @csrf
            <button type="submit" class="btn btn-secondary btn-sm">Sign out</button>
        </form>
    </div>
@endsection

@section('content')
    <div class="theme-settings">
        <h2>Appearance</h2>
        <div class="theme-toggle" role="group" aria-label="Choose theme">
            <button type="button" data-theme-choice="light">☀️ Light</button>
            <button type="button" data-theme-choice="dark">🌙 Dark</button>
            <button type="button" data-theme-choice="system">💻 System</button>
        </div>
    </div>

    <h2 style="font-size:18px;margin:0 0 16px">Stats</h2>
    <div class="stats-row">
        <div class="stat-card">
            <div class="value">{{ $stats['vocabulary_count'] }}</div>
            <div class="label">Vocabulary</div>
        </div>
        <div class="stat-card">
            <div class="value">{{ $stats['media_count'] }}</div>
            <div class="label">Listening</div>
        </div>
        <div class="stat-card">
            <div class="value">
                {{ $stats['average_score_percent'] !== null ? $stats['average_score_percent'].'%' : '—' }}
            </div>
            <div class="label">Avg score</div>
        </div>
    </div>

    <h2 style="font-size:18px;margin:0 0 16px">Attempt history</h2>

    @if (empty($history))
        <div class="empty-state" style="padding:24px">
            No attempts yet.<br>Try a vocabulary quiz or a listening exercise!
        </div>
    @else
        @foreach ($history as $item)
            <div class="card" style="display:flex;align-items:center;gap:12px">
                <div class="list-item-icon" style="margin:0">
                    {{ ($item['kind'] ?? '') === 'listening' ? '🎧' : '❓' }}
                </div>
                <div style="flex:1;min-width:0">
                    <p class="card-title" style="font-size:15px">{{ $item['title'] }}</p>
                    @if (!empty($item['completed_at']))
                        <p class="card-subtitle">{{ $item['completed_at']->format('d/m/Y') }}</p>
                    @endif
                </div>
                <span class="score-badge">{{ $item['score'] }}/{{ $item['total'] }}</span>
            </div>
        @endforeach
    @endif
@endsection
