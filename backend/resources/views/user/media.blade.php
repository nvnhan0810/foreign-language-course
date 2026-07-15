@extends('user.layout')

@section('title', 'Listen — FLC')
@section('heading', 'Listen')

@section('content')
    <section
        class="youtube-add card"
        data-youtube-add
        data-preview-url="{{ route('user.home.media.youtube.preview') }}"
    >
        <p class="card-title" style="margin-bottom:12px">Add from YouTube</p>
        <p class="muted" style="margin-top:0;margin-bottom:14px;font-size:13px">
            Paste a YouTube link (watch, youtu.be, or Shorts), fetch the title, then save.
        </p>

        <div class="form-group">
            <label for="youtube-url">YouTube URL</label>
            <div class="youtube-add-row">
                <input
                    type="url"
                    id="youtube-url"
                    class="form-control"
                    name="url_preview"
                    placeholder="https://www.youtube.com/watch?v=..."
                    autocomplete="off"
                    data-youtube-url
                    value="{{ old('url') }}"
                >
                <button type="button" class="btn btn-secondary" data-youtube-fetch>Fetch</button>
            </div>
            <p class="youtube-add-status muted" data-youtube-status hidden></p>
        </div>

        <div class="youtube-preview" data-youtube-preview @if (!old('url')) hidden @endif>
            <form action="{{ route('user.home.media.youtube.store') }}" method="POST" class="flc-form-submit">
                @csrf
                <input type="hidden" name="url" value="{{ old('url') }}" data-youtube-url-hidden>

                <div class="youtube-preview-body">
                    <img
                        src=""
                        alt=""
                        class="youtube-preview-thumb"
                        data-youtube-thumb
                        hidden
                    >
                    <div class="youtube-preview-fields">
                        <div class="form-group" style="margin-bottom:12px">
                            <label for="youtube-title">Title</label>
                            <input
                                type="text"
                                id="youtube-title"
                                name="title"
                                class="form-control"
                                required
                                maxlength="255"
                                value="{{ old('title') }}"
                                data-youtube-title
                            >
                        </div>
                        <div class="youtube-preview-meta">
                            <div class="form-group" style="margin-bottom:0">
                                <label for="youtube-difficulty">Difficulty</label>
                                <select id="youtube-difficulty" name="difficulty" class="form-control">
                                    <option value="beginner" @selected(old('difficulty') === 'beginner')>Beginner</option>
                                    <option value="intermediate" @selected(old('difficulty', 'intermediate') === 'intermediate')>Intermediate</option>
                                    <option value="advanced" @selected(old('difficulty') === 'advanced')>Advanced</option>
                                </select>
                            </div>
                            <div class="form-group" style="margin-bottom:0">
                                <label for="youtube-frequency">Remind</label>
                                <select id="youtube-frequency" name="frequency" class="form-control">
                                    <option value="daily" @selected(old('frequency') === 'daily')>Daily</option>
                                    <option value="weekly" @selected(old('frequency', 'weekly') === 'weekly')>Weekly</option>
                                    <option value="monthly" @selected(old('frequency') === 'monthly')>Monthly</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn btn-block" style="margin-top:16px" data-youtube-save>Save</button>
            </form>
        </div>
    </section>

    @if ($items->isEmpty())
        <div class="empty-state">
            No media yet. Add a YouTube video above to get started.
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
