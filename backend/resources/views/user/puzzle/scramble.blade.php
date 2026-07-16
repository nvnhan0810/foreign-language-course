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
            $displayElapsed = $elapsedSeconds;
            if ($displayElapsed === null && $answered && $startedAt) {
                $displayElapsed = max(0, time() - (int) $startedAt);
            }
            $formatTime = function (?int $seconds): string {
                $seconds = max(0, (int) $seconds);
                return sprintf('%02d:%02d', intdiv($seconds, 60), $seconds % 60);
            };
        @endphp

        <div class="puzzle-screen {{ $answered ? 'is-resolved' : 'is-playing' }} {{ $wasCorrect === true ? 'is-win' : '' }} {{ $wasCorrect === false ? 'is-lose' : '' }}">
            <div class="puzzle-topbar">
                <a href="{{ route('user.home.puzzle') }}" class="puzzle-close" aria-label="Back to modes" data-puzzle-exit data-confirm="Leave this round?">✕</a>
                <div class="puzzle-timer-pill {{ $answered ? 'is-frozen' : '' }}">
                    <span class="puzzle-timer-label">TIME</span>
                    <span
                        class="puzzle-timer-value"
                        id="puzzle-timer"
                        @if (!$answered && $startedAt)
                            data-puzzle-timer
                            data-started-at="{{ $startedAt }}"
                        @endif
                    >{{ $formatTime($answered ? $displayElapsed : ($startedAt ? max(0, time() - (int) $startedAt) : 0)) }}</span>
                </div>
                <div class="puzzle-meta-pill">{{ $wordLength }} LTR</div>
            </div>

            <div class="puzzle-screen-body">
                <div class="puzzle-arena">
                    <p class="puzzle-arena-prompt">Unscramble</p>
                    <div class="puzzle-letter-row" aria-label="Scrambled letters">
                        @foreach ($letters as $index => $letter)
                            <span class="puzzle-letter-chip" style="--i: {{ $index }}">{{ strtoupper($letter) }}</span>
                        @endforeach
                    </div>
                </div>

                @if ($hintUsed && !$answered)
                    <div class="puzzle-hint-card">
                        <div class="puzzle-hint-label">Hint</div>
                        @if (!empty($hint['part_of_speech']))
                            <p class="puzzle-hint-pos">{{ $hint['part_of_speech'] }}</p>
                        @endif
                        <p class="puzzle-hint-text">{{ $hint['definition'] ?? '' }}</p>
                    </div>
                @endif

                @if (!$answered)
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
                        <div class="puzzle-action-row">
                            <button type="submit" class="btn puzzle-btn-submit">Submit</button>
                            <button
                                type="submit"
                                class="btn btn-secondary puzzle-btn-help"
                                formaction="{{ route('user.home.puzzle.scramble.hint') }}"
                                formnovalidate
                                id="puzzle-help-btn"
                                data-puzzle-help
                                data-help-delay-ms="15000"
                                data-help-label="Help"
                                @if ($hintUsed) data-puzzle-help-used @endif
                                disabled
                            >Help</button>
                        </div>
                    </form>
                @else
                    <div class="puzzle-result {{ $wasCorrect ? 'is-win' : 'is-lose' }}">
                        <div class="puzzle-result-banner">
                            <span class="puzzle-result-title">{{ $wasCorrect ? 'Nice!' : 'Not quite' }}</span>
                            <span class="puzzle-result-time">{{ $formatTime($displayElapsed) }}</span>
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

                    <form action="{{ route('user.home.puzzle.scramble.next') }}" method="POST" class="flc-form-submit puzzle-next-form">
                        @csrf
                        <button type="submit" class="btn btn-block puzzle-btn-play">Next round →</button>
                    </form>
                @endif
            </div>
        </div>
    @endif
@endsection
