@extends('user.layout')

@section('title', 'Hangman — FLC')
@section('hide_nav', true)
@section('hide_header', true)
@section('game_screen', true)

@section('content')
    @if (!$puzzle)
        <div class="puzzle-screen puzzle-screen-idle">
            <a href="{{ route('user.home.puzzle') }}" class="puzzle-close puzzle-exit-floating" aria-label="Back to modes" data-puzzle-exit data-confirm="Leave this round?">✕</a>
            <div class="puzzle-screen-body">
                <div class="puzzle-game-idle-art hangman-idle-art" aria-hidden="true">
                    <span>H</span><span>A</span><span>N</span><span>G</span><span>?</span>
                </div>
                <h2 class="puzzle-game-idle-title">Hangman</h2>
                <p class="puzzle-game-idle-sub">Read the clue, then guess letters. Six wrong guesses and you’re out.</p>
                <form action="{{ route('user.home.puzzle.hangman.next') }}" method="POST" class="flc-form-submit">
                    @csrf
                    <button type="submit" class="btn puzzle-btn-play">▶ Play</button>
                </form>
            </div>
        </div>
    @else
        @php
            $answered = $feedback !== null;
            $mask = is_array($puzzle['mask'] ?? null) ? $puzzle['mask'] : [];
            $maxWrong = (int) ($puzzle['max_wrong'] ?? \Flc\Puzzle\Domain\HangmanGrader::MAX_WRONG);
            $wrongCount = (int) ($puzzle['wrong_count'] ?? 0);
            $guessed = is_array($guessedLetters ?? null) ? $guessedLetters : [];
            $guessedLookup = array_fill_keys(array_map('strtolower', $guessed), true);
            $correctWord = strtolower((string) ($puzzle['correct_word'] ?? ''));
            $sessionCorrect = (int) ($sessionCorrect ?? 0);
            $bestCorrect = (int) ($bestCorrect ?? 0);
            $formatTime = function (?int $seconds): string {
                $seconds = max(0, (int) $seconds);
                return sprintf('%02d:%02d', intdiv($seconds, 60), $seconds % 60);
            };
            $liveElapsed = $startedAt ? max(0, time() - (int) $startedAt) : 0;
            $clueDefinition = (string) ($puzzle['clue_definition'] ?? '');
            $cluePos = $puzzle['clue_part_of_speech'] ?? null;
            $rows = [
                ['q', 'w', 'e', 'r', 't', 'y', 'u', 'i', 'o', 'p'],
                ['a', 's', 'd', 'f', 'g', 'h', 'j', 'k', 'l'],
                ['z', 'x', 'c', 'v', 'b', 'n', 'm'],
            ];
        @endphp

        @include('user.partials.game-record-celebrate', ['celebrateRecord' => $celebrateRecord ?? null])

        <div class="puzzle-screen hangman-screen {{ $answered ? 'is-resolved' : 'is-playing' }} {{ $wasCorrect === true ? 'is-win' : '' }} {{ $wasCorrect === false ? 'is-lose' : '' }}">
            <div class="puzzle-topbar puzzle-topbar-score hangman-topbar">
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
                <div class="game-score-pill" title="Wins this run / personal best">
                    <span class="game-score-current">✓ {{ $sessionCorrect }}</span>
                    <span class="game-score-sep">·</span>
                    <span class="game-score-best">Best {{ $bestCorrect }}</span>
                </div>
            </div>

            <div class="puzzle-screen-scroll">
                <div class="hangman-figure" data-hangman-wrong="{{ $wrongCount }}" aria-label="{{ $wrongCount }} of {{ $maxWrong }} wrong guesses">
                    <svg class="hangman-svg" viewBox="0 0 120 160" role="img" aria-hidden="true">
                        <line class="hangman-part is-base" x1="10" y1="150" x2="90" y2="150" />
                        <line class="hangman-part is-pole" x1="30" y1="150" x2="30" y2="20" />
                        <line class="hangman-part is-beam" x1="30" y1="20" x2="85" y2="20" />
                        <line class="hangman-part is-rope {{ $wrongCount >= 1 ? 'is-on' : '' }}" x1="85" y1="20" x2="85" y2="40" />
                        <circle class="hangman-part is-head {{ $wrongCount >= 2 ? 'is-on' : '' }}" cx="85" cy="52" r="12" />
                        <line class="hangman-part is-body {{ $wrongCount >= 3 ? 'is-on' : '' }}" x1="85" y1="64" x2="85" y2="100" />
                        <line class="hangman-part is-arm-l {{ $wrongCount >= 4 ? 'is-on' : '' }}" x1="85" y1="74" x2="68" y2="92" />
                        <line class="hangman-part is-arm-r {{ $wrongCount >= 5 ? 'is-on' : '' }}" x1="85" y1="74" x2="102" y2="92" />
                        <line class="hangman-part is-leg-l {{ $wrongCount >= 6 ? 'is-on' : '' }}" x1="85" y1="100" x2="70" y2="124" />
                        <line class="hangman-part is-leg-r {{ $wrongCount >= 6 ? 'is-on' : '' }}" x1="85" y1="100" x2="100" y2="124" />
                    </svg>
                    <p class="hangman-lives">{{ max(0, $maxWrong - $wrongCount) }} left</p>
                </div>

                <div class="hangman-clue-card" aria-label="Clue">
                    <div class="puzzle-hint-label">Clue</div>
                    @if (!empty($cluePos))
                        <p class="puzzle-hint-pos">{{ $cluePos }}</p>
                    @endif
                    <p class="puzzle-hint-text">{{ $clueDefinition }}</p>
                </div>

                <div
                    class="hangman-word"
                    data-hangman-board
                    data-resolved="{{ $answered ? '1' : '0' }}"
                    aria-label="Hidden word"
                >
                    @foreach ($mask as $slot)
                        <span class="hangman-slot {{ $slot !== null ? 'is-revealed' : '' }}">
                            {{ $slot !== null ? strtoupper($slot) : '' }}
                        </span>
                    @endforeach
                </div>

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

            <div class="puzzle-screen-footer hangman-footer">
                @if (! $answered)
                    <form
                        action="{{ route('user.home.puzzle.hangman.guess') }}"
                        method="POST"
                        class="flc-form-submit hangman-guess-form"
                        data-hangman-form
                    >
                        @csrf
                        <input type="hidden" name="letter" value="" data-hangman-input maxlength="1">
                    </form>

                    <div class="hangman-keyboard" data-hangman-keyboard aria-label="Letter keyboard">
                        @foreach ($rows as $row)
                            <div class="hangman-keyboard-row">
                                @foreach ($row as $key)
                                    @php
                                        $used = isset($guessedLookup[$key]);
                                        $hit = $used && str_contains($correctWord, $key);
                                        $miss = $used && ! $hit;
                                    @endphp
                                    <button
                                        type="button"
                                        class="hangman-key {{ $hit ? 'is-hit' : '' }} {{ $miss ? 'is-miss' : '' }}"
                                        data-hangman-key="{{ $key }}"
                                        @if ($used) disabled @endif
                                    >{{ strtoupper($key) }}</button>
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                @else
                    <form action="{{ route('user.home.puzzle.hangman.next') }}" method="POST" class="flc-form-submit puzzle-next-form">
                        @csrf
                        <button type="submit" class="btn btn-block puzzle-btn-play">Next round →</button>
                    </form>
                @endif
            </div>
        </div>
    @endif
@endsection
