@extends('user.layout')

@section('title', 'Listen — FLC')
@section('heading', 'Listen')

@section('content')
    @if ($items->isEmpty())
        <div class="empty-state">
            No media yet. Add some from the admin page.
        </div>
    @else
        <div class="media-filters" role="group" aria-label="Filter by difficulty">
            <button type="button" class="media-filter active" data-difficulty-filter="all">All</button>
            <button type="button" class="media-filter" data-difficulty-filter="beginner">Beginner</button>
            <button type="button" class="media-filter" data-difficulty-filter="intermediate">Intermediate</button>
            <button type="button" class="media-filter" data-difficulty-filter="advanced">Advanced</button>
        </div>

        <p class="media-filter-empty muted" hidden>No media at this difficulty.</p>

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
