@extends('user.layout')

@section('title', 'Quiz — FLC')
@section('heading', 'Quiz')

@section('content')
    <p class="muted" style="margin-top:0;font-weight:600">Quiz từ vựng (ôn từ đã lưu)</p>

    @if (!$question)
        <div style="text-align:center;padding:40px 0">
            <form action="{{ route('user.home.quiz.next') }}" method="POST" class="flc-form-submit">
                @csrf
                <button type="submit" class="btn">▶ Bắt đầu Quiz</button>
            </form>
        </div>
    @else
        <div class="card" style="background:rgba(67,97,238,0.08);text-align:center">
            <p style="color:var(--primary);font-weight:700;margin:0 0 8px">
                {{ ($question['question_type'] ?? '') === 'word_to_definition' ? 'Chọn nghĩa đúng' : 'Chọn từ đúng' }}
            </p>
            <p class="quiz-prompt" style="margin:0">{{ $question['prompt'] ?? '' }}</p>
        </div>

        @if ($feedback === null)
            @foreach ($question['options'] ?? [] as $option)
                <form action="{{ route('user.home.quiz.answer') }}" method="POST" class="flc-form-submit">
                    @csrf
                    <input type="hidden" name="vocabulary_id" value="{{ $question['vocabulary_id'] ?? '' }}">
                    <input type="hidden" name="question_type" value="{{ $question['question_type'] ?? '' }}">
                    <input type="hidden" name="prompt" value="{{ $question['prompt'] ?? '' }}">
                    <input type="hidden" name="correct_answer" value="{{ $question['correct_answer'] ?? '' }}">
                    <input type="hidden" name="choice" value="{{ $option }}">
                    <button type="submit" class="quiz-option">{{ $option }}</button>
                </form>
            @endforeach
        @else
            @foreach ($question['options'] ?? [] as $option)
                @php
                    $isCorrect = strtolower(trim($option)) === strtolower(trim($question['correct_answer'] ?? ''));
                    $class = $wasCorrect && $isCorrect ? 'correct' : (!$wasCorrect && $isCorrect ? 'correct' : '');
                @endphp
                <div class="quiz-option {{ $class }}" style="cursor:default">{{ $option }}</div>
            @endforeach

            <div class="alert {{ $wasCorrect ? 'alert-success' : 'alert-error' }}" style="margin-top:16px">
                {{ $feedback }}
            </div>

            <form action="{{ route('user.home.quiz.next') }}" method="POST" class="flc-form-submit" style="margin-top:16px">
                @csrf
                <button type="submit" class="btn btn-block">Câu tiếp theo</button>
            </form>
        @endif
    @endif
@endsection
