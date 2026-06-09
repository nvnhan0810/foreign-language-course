@extends('user.layout')

@section('title', $assessment->title . ' — FLC')
@section('heading', $assessment->title)
@section('hide_nav')

@section('content')
    @php
        $backUrl = $assessment->mediaItem
            ? route('user.home.media.show', $assessment->mediaItem)
            : route('user.home.media');
    @endphp
    <p><a href="{{ $backUrl }}">← Quay lại</a></p>

    @if ($result)
        <div class="card" style="text-align:center;background:rgba(67,97,238,0.08)">
            <p style="font-size:14px;color:var(--muted);margin:0 0 8px">Kết quả</p>
            <p style="font-size:32px;font-weight:700;margin:0;color:var(--primary)">
                {{ $result['score'] }}/{{ $result['total'] }}
            </p>
            <p style="margin:8px 0 0">{{ $result['percentage'] }}%</p>
        </div>
    @else
        <form action="{{ route('user.listening.submit', $assessment) }}" method="POST">
            @csrf
            @foreach ($questions as $q)
                <div class="card">
                    <p style="font-weight:600;margin:0 0 4px">Câu {{ $q->order }}</p>
                    <p style="margin:0 0 12px">{{ $q->prompt }}</p>

                    @if (is_array($q->options) && count($q->options) > 0)
                        @foreach ($q->options as $option)
                            <label style="display:block;margin-bottom:8px;cursor:pointer">
                                <input type="radio" name="answers[{{ $q->id }}]" value="{{ $option }}" required>
                                {{ $option }}
                            </label>
                        @endforeach
                    @else
                        <input
                            type="text"
                            name="answers[{{ $q->id }}]"
                            class="form-control"
                            placeholder="Nhập câu trả lời..."
                            required
                        >
                    @endif
                </div>
            @endforeach

            <button type="submit" class="btn btn-block">Nộp bài</button>
        </form>
    @endif
@endsection
