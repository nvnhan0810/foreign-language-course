@extends('user.layout')

@section('title', 'Cá nhân — FLC')

@section('header')
    <div class="profile-header">
        <div class="profile-avatar">
            {{ strtoupper(substr($user->name ?: $user->email, 0, 1)) }}
        </div>
        <h2 style="margin:0;font-size:20px">{{ $user->name ?: 'Người dùng' }}</h2>
        <p style="margin:4px 0 0;opacity:0.8;font-size:14px">{{ $user->email }}</p>
        <form action="{{ route('user.logout') }}" method="POST" style="margin-top:16px">
            @csrf
            <button type="submit" class="btn btn-secondary btn-sm">Đăng xuất</button>
        </form>
    </div>
@endsection

@section('content')
    <h2 style="font-size:18px;margin:0 0 16px">Thống kê</h2>
    <div class="stats-row">
        <div class="stat-card">
            <div class="value">{{ $stats['vocabulary_count'] }}</div>
            <div class="label">Từ vựng</div>
        </div>
        <div class="stat-card">
            <div class="value">{{ $stats['media_count'] }}</div>
            <div class="label">Bài nghe</div>
        </div>
        <div class="stat-card">
            <div class="value">
                {{ $stats['average_score_percent'] !== null ? $stats['average_score_percent'].'%' : '—' }}
            </div>
            <div class="label">Điểm TB</div>
        </div>
    </div>

    <h2 style="font-size:18px;margin:0 0 16px">Lịch sử làm bài</h2>

    @if (empty($history))
        <div class="empty-state" style="padding:24px">
            Chưa có bài làm nào.<br>Hãy thử quiz từ vựng hoặc bài nghe!
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
