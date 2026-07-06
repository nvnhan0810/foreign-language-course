@extends('user.layout')

@section('title', 'Nghe — FLC')
@section('heading', 'Nghe')

@section('content')
    @if ($items->isEmpty())
        <div class="empty-state">
            Chưa có media. Thêm từ trang admin.
        </div>
    @else
        @foreach ($items as $media)
            <a href="{{ route('user.home.media.show', $media) }}" class="list-item">
                <div class="list-item-icon">
                    {{ in_array($media->type, ['youtube']) ? '▶️' : '🎵' }}
                </div>
                <div class="list-item-body">
                    <p class="title">{{ $media->title }}</p>
                    <p class="subtitle">
                        <span class="difficulty-tag difficulty-tag--{{ $media->difficulty }}">{{ $media->difficultyLabel() }}</span>
                        · {{ strtoupper($media->type) }}
                    </p>
                </div>
                <span class="chevron">›</span>
            </a>
        @endforeach
    @endif
@endsection
