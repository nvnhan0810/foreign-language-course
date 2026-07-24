@extends('user.layout')

@section('title', 'Scramble — FLC')
@section('hide_nav', true)
@section('hide_header', true)
@section('game_screen', true)

@section('content')
    @if (!$puzzle)
        <div class="puzzle-screen puzzle-screen-idle">
            <a href="{{ route('user.home.puzzle') }}" class="puzzle-close puzzle-exit-floating" aria-label="Back to modes" data-puzzle-exit data-confirm="Leave this round?">✕</a>
            <div class="puzzle-screen-body">
                <div class="puzzle-game-idle-art" aria-hidden="true">
                    <span>S</span><span>C</span><span>R</span><span>A</span><span>M</span>
                </div>
                <h2 class="puzzle-game-idle-title">Scramble</h2>
                <p class="puzzle-game-idle-sub">Unscramble letters. Beat the clock.</p>
                <form action="{{ route('user.home.puzzle.scramble.next') }}" method="POST" class="flc-form-submit">
                    @csrf
                    <button type="submit" class="btn puzzle-btn-play">▶ Play</button>
                </form>
            </div>
        </div>
    @else
        @php
            $answered = $feedback !== null;
            $scrambled = (string) ($puzzle['scrambled'] ?? '');
            $letters = $scrambled !== '' ? str_split($scrambled) : [];
            $wordLength = (int) ($puzzle['word_length'] ?? count($letters));
            $hintUsed = is_array($hint);
            $hintDefinition = (string) ($hint['definition'] ?? $puzzle['hint_definition'] ?? '');
            $hintPos = $hint['part_of_speech'] ?? $puzzle['hint_part_of_speech'] ?? null;
            $sessionCorrect = (int) ($sessionCorrect ?? 0);
            $bestCorrect = (int) ($bestCorrect ?? 0);
            $formatTime = function (?int $seconds): string {
                $seconds = max(0, (int) $seconds);
                return sprintf('%02d:%02d', intdiv($seconds, 60), $seconds % 60);
            };
            $liveElapsed = $startedAt ? max(0, time() - (int) $startedAt) : 0;
        @endphp

        @include('user.partials.game-record-celebrate', ['celebrateRecord' => $celebrateRecord ?? null])

        <div class="puzzle-screen {{ $answered ? 'is-resolved' : 'is-playing' }} {{ $wasCorrect === true ? 'is-win' : '' }} {{ $wasCorrect === false ? 'is-lose' : '' }}">
            <div class="puzzle-topbar puzzle-topbar-score">
                <a href="{{ route('user.home.puzzle') }}" class="puzzle-close" aria-label="Back to modes" data-puzzle-exit data-confirm="Leave this round?">✕</a>
                <div class="puzzle-timer-pill">
                    <span class="puzzle-timer-label">TIME</span>
                    <span
                        class="puzzle-timer-value"
                        id="puzzle-timer"
                        @if ($startedAt)
                            data-puzzle-timer
                            data-started-at="{{ $startedAt }}"
                        @endif
                    >{{ $formatTime($liveElapsed) }}</span>
                </div>
                <div class="game-score-pill" title="Correct this run / personal best">
                    <span class="game-score-current">✓ {{ $sessionCorrect }}</span>
                    <span class="game-score-sep">·</span>
                    <span class="game-score-best">Best {{ $bestCorrect }}</span>
                </div>
            </div>

            <div class="puzzle-screen-scroll">
                <div class="puzzle-arena">
                    <p class="puzzle-arena-prompt">Unscramble</p>
                    <div class="puzzle-letter-row" aria-label="Scrambled letters">
                        @foreach ($letters as $index => $letter)
                            <span class="puzzle-letter-chip" style="--i: {{ $index }}">{{ strtoupper($letter) }}</span>
                        @endforeach
                    </div>
                </div>

                @if (! $answered)
                    <div
                        class="puzzle-hint-countdown"
                        data-puzzle-hint-countdown
                        data-help-delay-ms="15000"
                        @if ($wordStartedAt) data-word-started-at="{{ $wordStartedAt }}" @endif
                        @if ($hintUsed) hidden @endif
                        aria-live="polite"
                    >
                        <span class="puzzle-hint-countdown-label">Hint in</span>
                        <span class="puzzle-hint-countdown-value" data-puzzle-hint-countdown-value>15</span>
                        <span class="puzzle-hint-countdown-unit">s</span>
                    </div>
                    <div
                        class="puzzle-hint-card"
                        data-puzzle-auto-hint
                        data-help-delay-ms="15000"
                        @if ($wordStartedAt) data-word-started-at="{{ $wordStartedAt }}" @endif
                        @if ($hintUsed) data-puzzle-hint-ready @endif
                        @unless ($hintUsed) hidden @endunless
                    >
                        <div class="puzzle-hint-label">Hint</div>
                        @if (!empty($hintPos))
                            <p class="puzzle-hint-pos">{{ $hintPos }}</p>
                        @endif
                        <p class="puzzle-hint-text">{{ $hintDefinition }}</p>
                    </div>
                @endif

                @if ($answered)
                    <div class="puzzle-result {{ $wasCorrect ? 'is-win' : 'is-lose' }}">
                        <div class="puzzle-result-banner">
                            <span class="puzzle-result-title">{{ $wasCorrect ? 'Nice!' : 'Not quite' }}</span>
                            <span class="puzzle-result-time">✓ {{ $sessionCorrect }} · {{ $formatTime($elapsedSeconds ?? $liveElapsed) }}</span>
                        </div>
                        <p class="puzzle-result-msg">{{ $feedback }}</p>
                    </div>

                    @if ($reveal)
                        <div class="card puzzle-reveal-card">
                            <div class="vocab-detail-header">
                                <div class="vocab-detail-title">
                                    <p class="card-title" style="margin:0">{{ $reveal->word }}</p>
                                    @if ($reveal->phonetic)
                                        <p class="card-subtitle" style="font-style:italic;margin-top:6px">{{ $reveal->phonetic }}</p>
                                    @endif
                                </div>
                                <div class="vocab-card-actions">
                                    <button
                                        type="button"
                                        class="btn-icon flc-pronounce"
                                        data-pronounce-url="{{ route('user.home.dictionary.pronounce', $reveal->word) }}"
                                        data-word="{{ $reveal->word }}"
                                        title="Pronounce"
                                        aria-label="Pronounce"
                                    >🔊</button>
                                </div>
                            </div>

                            @include('user.partials.dictionary-entry-tabs', [
                                'meanings' => is_array($reveal->meanings) ? $reveal->meanings : [],
                                'synonyms' => [],
                                'antonyms' => [],
                                'extraExamples' => $reveal->examples,
                                'preferDetail' => true,
                            ])
                        </div>
                    @endif
                @endif
            </div>

            <div class="puzzle-screen-footer">
                @if (! $answered)
                    <form action="{{ route('user.home.puzzle.scramble.answer') }}" method="POST" class="flc-form-submit puzzle-answer-form">
                        @csrf
                        <input
                            id="scramble-answer"
                            type="text"
                            name="answer"
                            class="puzzle-answer-input"
                            autocomplete="off"
                            autocapitalize="off"
                            spellcheck="false"
                            maxlength="40"
                            required
                            autofocus
                            placeholder="Your guess"
                            aria-label="Your guess"
                        >
                        <button type="submit" class="btn btn-block puzzle-btn-submit">Submit</button>
                    </form>
                @else
                    <form action="{{ route('user.home.puzzle.scramble.next') }}" method="POST" class="flc-form-submit puzzle-next-form">
                        @csrf
                        <button type="submit" class="btn btn-block puzzle-btn-play">Next round →</button>
                    </form>
                @endif
            </div>
        </div>
    @endif
@endsection
