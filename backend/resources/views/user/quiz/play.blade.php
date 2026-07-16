@extends('user.layout')

@section('title', 'Quiz — FLC')
@section('hide_nav', true)
@section('hide_header', true)
@section('game_screen', true)

@section('content')
    @if (!$question)
        <div class="puzzle-screen puzzle-screen-idle">
            <a href="{{ route('user.home.quiz') }}" class="puzzle-close puzzle-exit-floating" aria-label="Back to games" data-puzzle-exit data-confirm="Leave the quiz?">✕</a>
            <div class="puzzle-screen-body">
                <div class="puzzle-game-idle-art" aria-hidden="true">
                    <span>?</span><span>A</span><span>B</span><span>C</span><span>?</span>
                </div>
                <h2 class="puzzle-game-idle-title">Vocabulary Quiz</h2>
                <p class="puzzle-game-idle-sub">Pick the right answer. Review your saved words.</p>
                <form action="{{ route('user.home.quiz.next') }}" method="POST" class="flc-form-submit">
                    @csrf
                    <button type="submit" class="btn puzzle-btn-play">▶ Play</button>
                </form>
            </div>
        </div>
    @else
        @php
            $answered = $feedback !== null;
            $isWordToDef = ($question['question_type'] ?? '') === 'word_to_definition';
        @endphp

        <div class="puzzle-screen {{ $answered ? 'is-resolved' : 'is-playing' }} {{ $wasCorrect === true ? 'is-win' : '' }} {{ $wasCorrect === false ? 'is-lose' : '' }}">
            <div class="puzzle-topbar">
                <a href="{{ route('user.home.quiz') }}" class="puzzle-close" aria-label="Back to games" data-puzzle-exit data-confirm="Leave the quiz?">✕</a>
                <div class="puzzle-topbar-title">Quiz</div>
                <span class="puzzle-topbar-spacer" aria-hidden="true"></span>
            </div>

            <div class="puzzle-screen-body">
                <div class="puzzle-arena">
                    <p class="puzzle-arena-prompt">{{ $isWordToDef ? 'Choose the meaning' : 'Choose the word' }}</p>
                    <p class="quiz-prompt quiz-game-prompt">{{ $question['prompt'] ?? '' }}</p>
                </div>

                <div class="quiz-game-options">
                    @if (!$answered)
                        @foreach ($question['options'] ?? [] as $option)
                            <form action="{{ route('user.home.quiz.answer') }}" method="POST" class="flc-form-submit">
                                @csrf
                                <input type="hidden" name="vocabulary_id" value="{{ $question['vocabulary_id'] ?? '' }}">
                                <input type="hidden" name="question_type" value="{{ $question['question_type'] ?? '' }}">
                                <input type="hidden" name="prompt" value="{{ $question['prompt'] ?? '' }}">
                                <input type="hidden" name="correct_answer" value="{{ $question['correct_answer'] ?? '' }}">
                                <input type="hidden" name="choice" value="{{ $option }}">
                                <button type="submit" class="qz-option">{{ $option }}</button>
                            </form>
                        @endforeach
                    @else
                        @foreach ($question['options'] ?? [] as $option)
                            @php
                                $isCorrect = strtolower(trim($option)) === strtolower(trim($question['correct_answer'] ?? ''));
                            @endphp
                            <div class="qz-option is-static {{ $isCorrect ? 'is-correct' : '' }}">{{ $option }}</div>
                        @endforeach
                    @endif
                </div>

                @if ($answered)
                    <div class="puzzle-result {{ $wasCorrect ? 'is-win' : 'is-lose' }}">
                        <div class="puzzle-result-banner">
                            <span class="puzzle-result-title">{{ $wasCorrect ? 'Correct!' : 'Wrong' }}</span>
                        </div>
                        <p class="puzzle-result-msg">{{ $feedback }}</p>
                    </div>

                    <form action="{{ route('user.home.quiz.next') }}" method="POST" class="flc-form-submit puzzle-next-form">
                        @csrf
                        <button type="submit" class="btn btn-block puzzle-btn-play">Next question →</button>
                    </form>
                @endif
            </div>
        </div>
    @endif
@endsection
