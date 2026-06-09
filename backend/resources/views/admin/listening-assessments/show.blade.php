@extends('admin.layout')

@section('title', $assessment->title)
@section('heading', $assessment->title)

@section('content')
<div class="toolbar">
    <span class="badge">{{ $assessment->type }}</span>
    <span class="badge badge-{{ $assessment->status }}">{{ $assessment->status }}</span>
    <form action="{{ route('admin.listening-assessments.regenerate', $assessment) }}" method="POST" class="inline-form">
        @csrf
        <button type="submit" class="btn btn-sm btn-secondary">Tạo lại câu hỏi</button>
    </form>
    <form action="{{ route('admin.listening-assessments.destroy', $assessment) }}" method="POST" class="inline-form" onsubmit="return confirm('Xóa bài này?')">
        @csrf @method('DELETE')
        <button type="submit" class="btn btn-sm btn-danger">Xóa</button>
    </form>
</div>

<div class="grid-2">
    <div class="card">
        <h3 style="margin-top:0">Thông tin</h3>
        <dl class="detail-list">
            <dt>Media</dt>
            <dd>
                @if ($assessment->mediaItem)
                    <a href="{{ route('admin.media-items.show', $assessment->mediaItem) }}">{{ $assessment->mediaItem->title }}</a>
                @else
                    —
                @endif
            </dd>
            <dt>User</dt><dd>{{ $assessment->user?->email }}</dd>
            <dt>Số câu hỏi</dt><dd>{{ $assessment->questions->count() }}</dd>
            <dt>Thời gian</dt><dd>{{ $assessment->time_limit_minutes }} phút</dd>
            @if ($assessment->generated_at)
                <dt>Tạo lúc</dt><dd>{{ $assessment->generated_at->format('d/m/Y H:i') }}</dd>
            @endif
            @if ($assessment->description)
                <dt>Mô tả</dt><dd>{{ $assessment->description }}</dd>
            @endif
        </dl>
    </div>

    <div class="card">
        <h3 style="margin-top:0">Lượt làm gần đây ({{ $assessment->attempts->count() }})</h3>
        @if ($assessment->attempts->isEmpty())
            <p class="muted">Chưa có lượt làm.</p>
        @else
            <table>
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Điểm</th>
                        <th>%</th>
                        <th>Hoàn thành</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($assessment->attempts as $attempt)
                        <tr>
                            <td>{{ $attempt->user?->email }}</td>
                            <td>{{ $attempt->score }}/{{ $attempt->total }}</td>
                            <td>{{ $attempt->percentage }}%</td>
                            <td>{{ $attempt->completed_at?->format('d/m/Y H:i') ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>

<div class="card answers-panel answers-hidden" id="assessment-answers-panel">
    <div class="answers-panel-header">
        <h3 style="margin-top:0">Câu hỏi ({{ $assessment->questions->count() }})</h3>
        <button
            type="button"
            class="answer-toggle-btn"
            data-answer-toggle="assessment-answers-panel"
            aria-pressed="false"
            title="Hiện đáp án"
        >
            <svg class="icon-eye icon-eye-show" viewBox="0 0 24 24" width="20" height="20" aria-hidden="true">
                <path fill="currentColor" d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/>
            </svg>
            <svg class="icon-eye icon-eye-hide" viewBox="0 0 24 24" width="20" height="20" aria-hidden="true">
                <path fill="currentColor" d="M12 7c2.76 0 5 2.24 5 5 0 .65-.13 1.26-.36 1.83l2.92 2.92c1.51-1.26 2.7-2.89 3.43-4.75-1.73-4.39-6-7.5-11-7.5-1.4 0-2.74.25-3.98.7l2.16 2.16C10.74 7.13 11.35 7 12 7zM2 4.27l2.28 2.28.46.46C3.08 8.3 1.78 10.02 1 12c1.73 4.39 6 7.5 11 7.5 1.55 0 3.03-.3 4.38-.84l.42.42L19.73 22 21 20.73 3.27 3 2 4.27zM7.53 9.8l1.55 1.55c-.05.21-.08.43-.08.65 0 1.66 1.34 3 3 3 .22 0 .44-.03.65-.08l1.55 1.55c-.67.33-1.41.53-2.2.53-2.76 0-5-2.24-5-5 0-.79.2-1.53.53-2.2zm4.31-.78 3.15 3.15.02-.16c0-1.66-1.34-3-3-3l-.17.01z"/>
            </svg>
            <span class="answer-toggle-label">Hiện đáp án</span>
        </button>
    </div>
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Loại</th>
                <th>Câu hỏi</th>
                <th>Đáp án đúng</th>
                <th>Giải thích</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($assessment->questions as $q)
                <tr>
                    <td>{{ $q->order }}</td>
                    <td><span class="badge">{{ $q->question_type }}</span></td>
                    <td>
                        {{ $q->prompt }}
                        @if ($q->options)
                            <ul class="options-list">
                                @foreach ($q->options as $opt)
                                    <li @if($opt === $q->correct_answer) data-correct="1" @endif>{{ $opt }}</li>
                                @endforeach
                            </ul>
                        @endif
                    </td>
                    <td class="answer-cell">
                        <div class="answer-block">
                            <span class="answer-text"><strong>{{ $q->correct_answer }}</strong></span>
                        </div>
                    </td>
                    <td class="answer-cell">
                        <div class="answer-block">
                            <span class="answer-text muted">{{ $q->explanation }}</span>
                        </div>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>

<p>
    @if ($assessment->mediaItem)
        <a href="{{ route('admin.media-items.show', $assessment->mediaItem) }}" class="btn btn-secondary">← Về media</a>
    @endif
    <a href="{{ route('admin.listening-assessments.index') }}" class="btn btn-secondary">Danh sách quiz/test/exam</a>
</p>

<script>
document.querySelectorAll('[data-answer-toggle]').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var panel = document.getElementById(btn.getAttribute('data-answer-toggle'));
        if (!panel) return;

        var hidden = panel.classList.toggle('answers-hidden');
        btn.setAttribute('aria-pressed', hidden ? 'false' : 'true');
        btn.setAttribute('title', hidden ? 'Hiện đáp án' : 'Ẩn đáp án');

        var label = btn.querySelector('.answer-toggle-label');
        if (label) {
            label.textContent = hidden ? 'Hiện đáp án' : 'Ẩn đáp án';
        }
    });
});
</script>
@endsection
