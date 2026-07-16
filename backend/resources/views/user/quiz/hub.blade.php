@extends('user.layout')

@section('title', 'Games — FLC')
@section('heading', 'Games')
@section('game_bg', true)

@section('content')
    <div class="puzzle-hub-body">
        <p class="puzzle-hub-kicker">Play &amp; learn</p>

        <div class="puzzle-mode-grid">
            <a href="{{ route('user.home.quiz.play', ['autostart' => 1]) }}" class="puzzle-mode-card puzzle-mode-live">
                <span class="puzzle-mode-thumb">
                    <img src="{{ asset('images/puzzle/quiz.svg') }}" alt="Quiz" loading="lazy">
                    <span class="puzzle-mode-tag puzzle-mode-tag-play">Play</span>
                </span>
                <span class="puzzle-mode-info">
                    <span class="puzzle-mode-title">Quiz</span>
                    <span class="puzzle-mode-desc">Multiple-choice review of saved words</span>
                </span>
            </a>

            <a href="{{ route('user.home.puzzle') }}" class="puzzle-mode-card puzzle-mode-live">
                <span class="puzzle-mode-thumb">
                    <img src="{{ asset('images/puzzle/puzzle.svg') }}" alt="Word Puzzle" loading="lazy">
                    <span class="puzzle-mode-tag puzzle-mode-tag-play">Play</span>
                </span>
                <span class="puzzle-mode-info">
                    <span class="puzzle-mode-title">Word Puzzle</span>
                    <span class="puzzle-mode-desc">Scramble &amp; more letter games</span>
                </span>
            </a>
        </div>
    </div>
@endsection
