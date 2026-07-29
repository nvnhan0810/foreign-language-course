@extends('user.layout')

@section('title', 'Word Puzzle — FLC')
@section('hide_nav', true)
@section('hide_header', true)
@section('game_screen', true)

@section('content')
    <div class="puzzle-screen puzzle-hub-screen">
        <div class="puzzle-topbar">
            <form action="{{ route('user.home.puzzle.exit') }}" method="POST" class="puzzle-exit-form">
                @csrf
                <button type="submit" class="puzzle-close" aria-label="Exit" data-puzzle-exit data-confirm="Exit Word Puzzle?">✕</button>
            </form>
            <div class="puzzle-topbar-title">Word Puzzle</div>
            <span class="puzzle-topbar-spacer" aria-hidden="true"></span>
        </div>

        <div class="puzzle-screen-body puzzle-hub-body">
            <p class="puzzle-hub-kicker">Choose a mode</p>

            <div class="puzzle-mode-grid">
                <a href="{{ route('user.home.puzzle.scramble', ['autostart' => 1]) }}" class="puzzle-mode-card puzzle-mode-live">
                    <span class="puzzle-mode-thumb">
                        <img src="{{ asset('images/puzzle/scramble.svg') }}" alt="Scramble" loading="lazy">
                        <span class="puzzle-mode-tag puzzle-mode-tag-play">Play</span>
                    </span>
                    <span class="puzzle-mode-info">
                        <span class="puzzle-mode-title">Scramble</span>
                        <span class="puzzle-mode-desc">Unscramble letters vs. the clock</span>
                    </span>
                </a>

                <a href="{{ route('user.home.puzzle.wordle', ['autostart' => 1]) }}" class="puzzle-mode-card puzzle-mode-live">
                    <span class="puzzle-mode-thumb">
                        <img src="{{ asset('images/puzzle/wordle.svg') }}" alt="Wordle" loading="lazy">
                        <span class="puzzle-mode-tag puzzle-mode-tag-play">Play</span>
                    </span>
                    <span class="puzzle-mode-info">
                        <span class="puzzle-mode-title">Wordle</span>
                        <span class="puzzle-mode-desc">Guess with a letter bank + color feedback</span>
                    </span>
                </a>

                <a href="{{ route('user.home.puzzle', ['mode' => 'hangman']) }}" class="puzzle-mode-card puzzle-mode-soon">
                    <span class="puzzle-mode-thumb">
                        <img src="{{ asset('images/puzzle/hangman.svg') }}" alt="Hangman" loading="lazy">
                        <span class="puzzle-mode-tag">Soon</span>
                    </span>
                    <span class="puzzle-mode-info">
                        <span class="puzzle-mode-title">Hangman</span>
                        <span class="puzzle-mode-desc">Guess letters from a clue</span>
                    </span>
                </a>

                <a href="{{ route('user.home.puzzle', ['mode' => 'word_search']) }}" class="puzzle-mode-card puzzle-mode-soon">
                    <span class="puzzle-mode-thumb">
                        <img src="{{ asset('images/puzzle/word-search.svg') }}" alt="Word Search" loading="lazy">
                        <span class="puzzle-mode-tag">Soon</span>
                    </span>
                    <span class="puzzle-mode-info">
                        <span class="puzzle-mode-title">Word Search</span>
                        <span class="puzzle-mode-desc">Find your words in a grid</span>
                    </span>
                </a>
            </div>
        </div>
    </div>
@endsection
