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

<div class="card">
    <h3 style="margin-top:0">Câu hỏi ({{ $assessment->questions->count() }})</h3>
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
                                    <li @if($opt === $q->correct_answer) class="correct" @endif>{{ $opt }}</li>
                                @endforeach
                            </ul>
                        @endif
                    </td>
                    <td><strong>{{ $q->correct_answer }}</strong></td>
                    <td class="muted">{{ Str::limit($q->explanation, 80) }}</td>
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
@endsection
