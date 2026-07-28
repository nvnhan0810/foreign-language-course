@extends('user.layout')

@section('title', 'Learn — FLC')
@section('heading', 'Learn')

@section('content')
    <div
        class="word-chat"
        data-word-chat
        data-prefill="{{ $prefill }}"
        data-messages-url="{{ url('/api/word-chat/messages') }}"
        data-send-url="{{ url('/api/word-chat/messages') }}"
        data-agent-url="{{ url('/api/word-chat/agent') }}"
        data-agent-ensure-url="{{ url('/api/word-chat/agent/ensure') }}"
        data-quiz-play-url="{{ route('user.home.quiz.play', ['autostart' => 1]) }}"
    >
        <div class="word-chat-agent-loading" data-word-chat-agent-loading hidden>
            <div class="word-chat-agent-loading-card">
                <p class="word-chat-agent-loading-title">Preparing chat…</p>
                <p class="muted word-chat-agent-loading-text" data-word-chat-agent-loading-text>Starting your word tutor session.</p>
            </div>
        </div>
        <div class="word-chat-messages" data-word-chat-messages role="log" aria-live="polite" aria-relevant="additions">
            <div class="word-chat-empty" data-word-chat-empty>
                <p class="word-chat-empty-title">Start a conversation</p>
                <p class="muted">Try: “What does <em>outlet</em> mean in this sentence?” or “Explain the difference between <em>affect</em> and <em>effect</em>.”</p>
            </div>
        </div>

        <form class="word-chat-composer" data-word-chat-form>
            <label class="sr-only" for="word-chat-input">Message</label>
            <input
                type="text"
                id="word-chat-input"
                class="form-control word-chat-input"
                data-word-chat-input
                maxlength="4000"
                placeholder="Ask about a word or phrase..."
                autocomplete="off"
                required
                disabled
                value="{{ old('word', $prefill) }}"
            >
            <button type="submit" class="btn word-chat-send" data-word-chat-send aria-label="Send message">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" aria-hidden="true">
                    <path fill="currentColor" d="M2.01 21 23 12 2.01 3 2 10l15 2-15 2z"/>
                </svg>
            </button>
        </form>
    </div>
@endsection
