@extends('user.layout')

@section('title', $media->title . ' — FLC')
@section('heading', $media->title)
@section('hide_nav')

@section('content')
    <p><a href="{{ route('user.home.media') }}">← Quay lại danh sách</a></p>

    @if ($media->type === 'youtube' && $media->source_id)
        <div style="position:relative;padding-bottom:56.25%;height:0;margin:16px 0;border-radius:12px;overflow:hidden">
            <iframe
                src="https://www.youtube.com/embed/{{ $media->source_id }}"
                style="position:absolute;top:0;left:0;width:100%;height:100%;border:0"
                allowfullscreen
            ></iframe>
        </div>
    @elseif ($media->url)
        <p style="margin:16px 0">
            <a href="{{ $media->url }}" target="_blank" rel="noopener" class="btn">Mở media</a>
        </p>
    @endif

    <h2 style="font-size:16px;margin:24px 0 12px">Bài kiểm tra nghe</h2>

    @if ($assessments->isEmpty())
        <p class="muted">Chưa có bài kiểm tra. Đợi admin xử lý media.</p>
    @else
        @foreach ($assessments as $assessment)
            <a href="{{ route('user.listening.show', $assessment) }}" class="list-item">
                <div class="list-item-icon">📝</div>
                <div class="list-item-body">
                    <p class="title">{{ $assessment->title }}</p>
                    <p class="subtitle">
                        {{ strtoupper($assessment->type) }}
                        · {{ $assessment->status === 'ready' ? 'Sẵn sàng' : $assessment->status }}
                    </p>
                </div>
                <span class="chevron">›</span>
            </a>
        @endforeach
    @endif
@endsection
