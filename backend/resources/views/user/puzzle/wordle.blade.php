@extends('user.layout')

@section('title', 'Wordle — FLC')
@section('hide_nav', true)
@section('hide_header', true)
@section('game_screen', true)

@section('content')
    @if (!$puzzle)
        <div class="puzzle-screen puzzle-screen-idle">
            <a href="{{ route('user.home.puzzle') }}" class="puzzle-close puzzle-exit-floating" aria-label="Back to modes" data-puzzle-exit data-confirm="Leave this round?">✕</a>
            <div class="puzzle-screen-body">
                <div class="puzzle-game-idle-art wordle-idle-art" aria-hidden="true">
                    <span>W</span><span>O</span><span>R</span><span>D</span><span>!</span>
                </div>
                <h2 class="puzzle-game-idle-title">Wordle</h2>
                <p class="puzzle-game-idle-sub">Use the letter bank — green = right spot, gold = wrong spot.</p>
                <form action="{{ route('user.home.puzzle.wordle.next') }}" method="POST" class="flc-form-submit">
                    @csrf
                    <button type="submit" class="btn puzzle-btn-play">▶ Play</button>
                </form>
            </div>
        </div>
    @else
        @php
            $answered = $feedback !== null;
            $wordLength = (int) ($puzzle['word_length'] ?? 5);
            $maxGuesses = (int) ($puzzle['max_guesses'] ?? 6);
            $guessRows = is_array($guesses) ? $guesses : [];
            $guessCount = count($guessRows);
            $sessionCorrect = (int) ($sessionCorrect ?? 0);
            $bestCorrect = (int) ($bestCorrect ?? 0);
            $formatTime = function (?int $seconds): string {
                $seconds = max(0, (int) $seconds);
                return sprintf('%02d:%02d', intdiv($seconds, 60), $seconds % 60);
            };
            $liveElapsed = $startedAt ? max(0, time() - (int) $startedAt) : 0;
            $keyboardLetters = is_array($puzzle['keyboard_letters'] ?? null) ? $puzzle['keyboard_letters'] : [];
            $letterKeys = array_keys($keyboardLetters);
            sort($letterKeys);
            $hintUsed = is_array($hint ?? null);
            $hintDefinition = (string) ($hint['definition'] ?? '');
            $hintPos = $hint['part_of_speech'] ?? null;
            $hintAtTs = is_numeric($hintAt ?? null) ? (int) $hintAt : null;
            $hintVisibleSec = \Flc\Puzzle\Domain\WordleGrader::HINT_VISIBLE_SECONDS;
            $hintCooldownSec = \Flc\Puzzle\Domain\WordleGrader::HINT_COOLDOWN_SECONDS;
            $elapsedSinceHint = $hintAtTs !== null ? max(0, time() - $hintAtTs) : null;
            $showHint = $hintUsed && $elapsedSinceHint !== null && $elapsedSinceHint < $hintVisibleSec;
            $canHelp = $hintAtTs === null || ($elapsedSinceHint !== null && $elapsedSinceHint >= $hintCooldownSec);
            $keyStates = [];
            foreach ($guessRows as $row) {
                foreach (($row['tiles'] ?? []) as $tile) {
                    $letter = strtolower((string) ($tile['letter'] ?? ''));
                    $state = (string) ($tile['state'] ?? 'absent');
                    if ($letter === '' || ! in_array($state, ['correct', 'present', 'absent'], true)) {
                        continue;
                    }
                    $rank = ['absent' => 1, 'present' => 2, 'correct' => 3];
                    if (! isset($keyStates[$letter]) || $rank[$state] > $rank[$keyStates[$letter]]) {
                        $keyStates[$letter] = $state;
                    }
                }
            }
        @endphp

        @include('user.partials.game-record-celebrate', ['celebrateRecord' => $celebrateRecord ?? null])

        <div class="puzzle-screen wordle-screen {{ $answered ? 'is-resolved' : 'is-playing' }} {{ $wasCorrect === true ? 'is-win' : '' }} {{ $wasCorrect === false ? 'is-lose' : '' }}">
            <div class="puzzle-topbar puzzle-topbar-score wordle-topbar">
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
                <div
                    class="wordle-board"
                    data-wordle-board
                    data-word-length="{{ $wordLength }}"
                    data-max-guesses="{{ $maxGuesses }}"
                    data-resolved="{{ $answered ? '1' : '0' }}"
                    @if ($letterKeys !== [])
                        data-wordle-letters="{{ json_encode($keyboardLetters, JSON_THROW_ON_ERROR) }}"
                    @endif
                    aria-label="Wordle board"
                >
                    @for ($row = 0; $row < $maxGuesses; $row++)
                        @php
                            $filled = $guessRows[$row] ?? null;
                            $isActive = ! $answered && $row === $guessCount;
                        @endphp
                        <div class="wordle-row {{ $isActive ? 'is-active' : '' }}" data-wordle-row="{{ $row }}">
                            @for ($col = 0; $col < $wordLength; $col++)
                                @php
                                    $tile = $filled['tiles'][$col] ?? null;
                                    $letter = $tile ? strtoupper((string) ($tile['letter'] ?? '')) : '';
                                    $state = $tile['state'] ?? '';
                                @endphp
                                <div
                                    class="wordle-tile {{ $state !== '' ? 'is-revealed is-'.$state : '' }} {{ $isActive ? 'is-editable' : '' }}"
                                    data-wordle-tile
                                    data-col="{{ $col }}"
                                >{{ $letter }}</div>
                            @endfor
                        </div>
                    @endfor
                </div>

                @if (! $answered)
                    <div
                        class="wordle-help-zone"
                        data-wordle-help
                        data-hint-at="{{ $hintAtTs ?? 0 }}"
                        data-hint-visible-ms="{{ $hintVisibleSec * 1000 }}"
                        data-hint-cooldown-ms="{{ $hintCooldownSec * 1000 }}"
                    >
                        <form
                            action="{{ route('user.home.puzzle.wordle.hint') }}"
                            method="POST"
                            class="wordle-help-form"
                            data-wordle-help-form
                        >
                            @csrf
                            <button
                                type="submit"
                                class="wordle-help-btn {{ $canHelp ? '' : 'is-cooldown' }}"
                                data-wordle-help-btn
                                aria-label="Show meaning"
                                @unless ($canHelp) disabled @endunless
                            ><span aria-hidden="true">?</span></button>
                        </form>
                    </div>
                @endif

                @if (! $answered && $hintUsed)
                    <div
                        class="puzzle-hint-card wordle-hint-card"
                        data-wordle-hint-card
                        @unless ($showHint) hidden @endunless
                    >
                        <div class="puzzle-hint-label">Meaning</div>
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

            <div class="puzzle-screen-footer wordle-footer">
                @if (! $answered)
                    <form
                        action="{{ route('user.home.puzzle.wordle.guess') }}"
                        method="POST"
                        class="flc-form-submit wordle-guess-form"
                        data-wordle-form
                    >
                        @csrf
                        <input type="hidden" name="guess" value="" data-wordle-input maxlength="5">
                    </form>

                    <div class="wordle-keyboard wordle-keyboard-compact" data-wordle-keyboard aria-label="Letter bank">
                        @if ($letterKeys !== [])
                            <p class="wordle-keyboard-hint">Pick from these letters</p>
                            <div class="wordle-keyboard-letters">
                                @foreach ($letterKeys as $key)
                                    @php
                                        $state = $keyStates[$key] ?? '';
                                        $maxUses = (int) ($keyboardLetters[$key] ?? 1);
                                    @endphp
                                    <button
                                        type="button"
                                        class="wordle-key {{ $state !== '' ? 'is-'.$state : '' }}"
                                        data-wordle-key="{{ $key }}"
                                        data-wordle-key-max="{{ $maxUses }}"
                                    >
                                        <span class="wordle-key-label">{{ strtoupper($key) }}</span>
                                        @if ($maxUses > 1)
                                            <span class="wordle-key-count" data-wordle-key-count>{{ $maxUses }}</span>
                                        @endif
                                    </button>
                                @endforeach
                            </div>
                        @endif
                        <div class="wordle-keyboard-actions">
                            <button type="button" class="wordle-key is-wide" data-wordle-key="enter">Enter</button>
                            <button type="button" class="wordle-key is-wide" data-wordle-key="backspace">⌫</button>
                        </div>
                    </div>
                @else
                    <form action="{{ route('user.home.puzzle.wordle.next') }}" method="POST" class="flc-form-submit puzzle-next-form">
                        @csrf
                        <button type="submit" class="btn btn-block puzzle-btn-play">Next round →</button>
                    </form>
                @endif
            </div>
        </div>
    @endif
@endsection
