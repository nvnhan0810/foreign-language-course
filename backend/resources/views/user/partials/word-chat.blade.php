@php
    $chatId = $chatId ?? 'word-chat';
    $prefill = $prefill ?? '';
    $lazy = ! empty($lazy);
    $variant = $variant ?? '';
    $emptyTitle = $emptyTitle ?? 'Start a conversation';
    $emptyHint = $emptyHint ?? 'Try: “What does <em>outlet</em> mean?” then “<em>save this word</em>” — or use <strong>Save word</strong> on the reply.';
@endphp
<div
    class="word-chat{{ $variant ? ' word-chat--' . $variant : '' }}"
    data-word-chat
    @if ($lazy) data-word-chat-lazy="1" @endif
    data-prefill="{{ $prefill }}"
    data-messages-url="{{ url('/api/word-chat/messages') }}"
    data-send-url="{{ url('/api/word-chat/messages') }}"
    data-agent-url="{{ url('/api/word-chat/agent') }}"
    data-agent-ensure-url="{{ url('/api/word-chat/agent/ensure') }}"
    data-quiz-play-url="{{ route('user.home.quiz.play', ['autostart' => 1]) }}"
    data-vocab-save-url="{{ url('/api/vocabularies') }}"
    data-vocab-show-url="{{ url('/home/vocab') }}"
    data-dictionary-pronounce-url="{{ url('/home/dictionary') }}"
>
    <div class="word-chat-agent-loading" data-word-chat-agent-loading hidden>
        <div class="word-chat-agent-loading-card">
            <p class="word-chat-agent-loading-title">Preparing chat…</p>
            <p class="muted word-chat-agent-loading-text" data-word-chat-agent-loading-text>Starting your word tutor session.</p>
        </div>
    </div>
    <div class="word-chat-messages" data-word-chat-messages role="log" aria-live="polite" aria-relevant="additions">
        <div class="word-chat-messages-inner" data-word-chat-messages-inner>
            <div class="word-chat-empty" data-word-chat-empty>
                <p class="word-chat-empty-title">{{ $emptyTitle }}</p>
                <p class="muted">{!! $emptyHint !!}</p>
            </div>
        </div>
    </div>

    <form class="word-chat-composer" data-word-chat-form>
        <label class="sr-only" for="{{ $chatId }}-input">Message</label>
        <textarea
            id="{{ $chatId }}-input"
            class="form-control word-chat-input"
            data-word-chat-input
            maxlength="4000"
            rows="1"
            placeholder="Ask about a word or phrase..."
            autocomplete="off"
            required
            disabled
        >{{ old('word', $prefill) }}</textarea>
        <button type="submit" class="btn word-chat-send" data-word-chat-send aria-label="Send message">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" aria-hidden="true">
                <path fill="currentColor" d="M2.01 21 23 12 2.01 3 2 10l15 2-15 2z"/>
            </svg>
        </button>
    </form>
</div>
