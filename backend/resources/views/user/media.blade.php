@extends('user.layout')

@section('title', 'Nghe — FLC')
@section('heading', 'Nghe')

@section('content')
    @if ($items->isEmpty())
        <div class="empty-state">
            Chưa có media. Thêm từ trang admin.
        </div>
    @else
        <div class="media-filters" role="group" aria-label="Lọc theo độ khó">
            <button type="button" class="media-filter active" data-difficulty-filter="all">Tất cả</button>
            <button type="button" class="media-filter" data-difficulty-filter="beginner">Cơ bản</button>
            <button type="button" class="media-filter" data-difficulty-filter="intermediate">Trung cấp</button>
            <button type="button" class="media-filter" data-difficulty-filter="advanced">Nâng cao</button>
        </div>

        <p class="media-filter-empty muted" hidden>Không có media ở mức độ này.</p>

        <div class="media-list" data-media-list>
            @foreach ($items as $media)
                <a
                    href="{{ route('user.home.media.show', $media) }}"
                    class="list-item"
                    data-difficulty="{{ $media->difficulty }}"
                >
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
        </div>
    @endif
@endsection
