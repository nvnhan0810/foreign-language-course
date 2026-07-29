@extends('user.layout')

@section('title', $media->title . ' — FLC')
@section('heading', $media->title)
@section('hide_nav', true)
@section('back_url', route('user.home.media'))

@section('content')
    <div class="media-show-page" data-media-word-chat>
        <div class="media-show-main">
            <div class="media-show-toolbar">
                <span class="difficulty-tag difficulty-tag--{{ $media->difficulty }}">{{ $media->difficultyLabel() }}</span>
                <button type="button" class="btn btn-sm btn-secondary media-word-chat-open-btn" data-media-word-chat-open>
                    Ask word
                </button>
            </div>

            @if ($media->type === 'youtube' && $media->source_id)
                <div class="video-embed">
                    <iframe
                        src="https://www.youtube.com/embed/{{ $media->source_id }}?playsinline=1&rel=0"
                        title="{{ $media->title }}"
                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; fullscreen"
                        referrerpolicy="strict-origin-when-cross-origin"
                        allowfullscreen
                        playsinline
                    ></iframe>
                </div>
            @elseif ($media->url)
                <p style="margin:16px 0">
                    <a href="{{ $media->url }}" target="_blank" rel="noopener" class="btn">Open media</a>
                </p>
            @endif

            @php
                $transcriptEditing = $errors->has('transcript');
                $hasTranscript = filled($media->transcript);
            @endphp
            <details class="transcript-collapse" @if ($transcriptEditing) open @endif data-transcript>
        <summary class="transcript-summary">
            <span class="transcript-summary-label">Transcript</span>
            <span class="transcript-summary-actions">
                <span class="transcript-save-status" data-transcript-status hidden aria-live="polite"></span>
                <span class="transcript-toolbar-view" data-transcript-toolbar-view @if ($transcriptEditing) hidden @endif>
                    <button type="button" class="btn btn-sm btn-secondary" data-transcript-edit>
                        {{ $hasTranscript ? 'Edit transcript' : 'Add transcript' }}
                    </button>
                </span>
                <span class="transcript-toolbar-edit" data-transcript-toolbar-edit @unless ($transcriptEditing) hidden @endunless>
                    <button
                        type="submit"
                        form="media-transcript-form"
                        class="btn btn-sm"
                        data-transcript-save
                    >Save</button>
                    <button type="button" class="btn btn-sm btn-secondary" data-transcript-cancel>Cancel</button>
                </span>
                <span class="transcript-collapse-icon" aria-hidden="true">▾</span>
            </span>
        </summary>
        <div class="transcript-collapse-body">
            <div class="transcript-scroll-panel" data-transcript-panel>
                <div class="transcript-view" data-transcript-view @if ($transcriptEditing) hidden @endif>
                    @if ($hasTranscript)
                        <div class="transcript-text" data-transcript-text>{{ $media->transcript }}</div>
                    @else
                        <p class="muted transcript-empty" data-transcript-empty>No transcript yet.</p>
                    @endif
                </div>

                <form
                    id="media-transcript-form"
                    method="POST"
                    action="{{ route('user.home.media.transcript', $media) }}"
                    class="transcript-form"
                    data-transcript-form
                    @unless ($transcriptEditing) hidden @endunless
                >
                    @csrf
                    @method('PUT')
                    <textarea
                        name="transcript"
                        class="transcript-textarea"
                        placeholder="Enter the video or audio transcript..."
                        spellcheck="false"
                    >{{ old('transcript', $media->transcript) }}</textarea>
                </form>
            </div>
            @if ($errors->has('transcript'))
                <p class="alert alert-error" style="margin-top:12px">{{ $errors->first('transcript') }}</p>
            @endif
        </div>
    </details>

    <h2 style="font-size:16px;margin:24px 0 12px">Listening quiz</h2>

    @if ($media->question_bank_status !== 'ready' || ($media->question_bank_count ?? 0) === 0)
        <p class="muted">
            Question bank is not ready yet
            ({{ $media->question_bank_status ?? 'pending' }}).
            Wait for an admin to finish analyzing the media.
        </p>
    @else
        <p class="muted" style="margin-bottom:12px">
            {{ $media->question_bank_count }} questions in the bank — each attempt picks a new random set.
        </p>

        @foreach ($sessionOptions as $option)
            <div class="session-row">
                <div class="list-item-body">
                    <p class="title">{{ $option['title'] }}</p>
                    <p class="subtitle">
                        {{ strtoupper($option['type']) }}
                        · {{ $option['question_count'] }} questions
                        @if (!empty($option['time_limit_minutes']))
                            · {{ $option['time_limit_minutes'] }} min
                        @endif
                    </p>
                </div>
                @if ($option['available'])
                    <form action="{{ route('user.listening.start', $media) }}" method="POST" class="flc-form-submit">
                        @csrf
                        <input type="hidden" name="type" value="{{ $option['type'] }}">
                        <button type="submit" class="btn btn-sm">Start</button>
                    </form>
                @else
                    <span class="muted">Not enough questions</span>
                @endif
            </div>
        @endforeach
    @endif
        </div>

        <div class="media-word-chat-backdrop" data-media-word-chat-backdrop hidden></div>
        <aside
            class="media-word-chat-sidebar"
            data-media-word-chat-panel
            hidden
            aria-hidden="true"
            aria-label="Word chat while listening"
        >
            <div class="media-word-chat-sidebar-head">
                <h2 class="media-word-chat-sidebar-title">Learn</h2>
                <button type="button" class="media-word-chat-close" data-media-word-chat-close aria-label="Close chat">×</button>
            </div>
            @include('user.partials.word-chat', [
                'chatId' => 'media-word-chat',
                'lazy' => true,
                'variant' => 'sidebar',
                'emptyTitle' => 'Ask while you listen',
                'emptyHint' => 'Try: “What does <em>outlet</em> mean in this video?” Select text in the transcript, open chat, and it will be prefilled.',
            ])
        </aside>
    </div>
@endsection
