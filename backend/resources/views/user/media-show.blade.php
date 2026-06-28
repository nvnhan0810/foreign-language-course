@extends('user.layout')

@section('title', $media->title . ' — FLC')
@section('heading', $media->title)
@section('hide_nav', true)
@section('back_url', route('user.home.media'))

@section('content')
    @if ($media->type === 'youtube' && $media->source_id)
        <div class="video-embed">
            <iframe
                src="https://www.youtube.com/embed/{{ $media->source_id }}"
                title="{{ $media->title }}"
                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                allowfullscreen
            ></iframe>
        </div>
    @elseif ($media->url)
        <p style="margin:16px 0">
            <a href="{{ $media->url }}" target="_blank" rel="noopener" class="btn">Mở media</a>
        </p>
    @endif

    <h2 style="font-size:16px;margin:24px 0 12px">Bài kiểm tra nghe</h2>

    @if ($media->question_bank_status !== 'ready' || ($media->question_bank_count ?? 0) === 0)
        <p class="muted">
            Ngân hàng câu hỏi chưa sẵn sàng
            ({{ $media->question_bank_status ?? 'pending' }}).
            Đợi admin phân tích xong media.
        </p>
    @else
        <p class="muted" style="margin-bottom:12px">
            {{ $media->question_bank_count }} câu hỏi trong ngân hàng — mỗi lần làm sẽ random câu mới.
        </p>

        @foreach ($sessionOptions as $option)
            <div class="session-row">
                <div class="list-item-body">
                    <p class="title">{{ $option['title'] }}</p>
                    <p class="subtitle">
                        {{ strtoupper($option['type']) }}
                        · {{ $option['question_count'] }} câu
                        @if (!empty($option['time_limit_minutes']))
                            · {{ $option['time_limit_minutes'] }} phút
                        @endif
                    </p>
                </div>
                @if ($option['available'])
                    <form action="{{ route('user.listening.start', $media) }}" method="POST" class="flc-form-submit">
                        @csrf
                        <input type="hidden" name="type" value="{{ $option['type'] }}">
                        <button type="submit" class="btn btn-sm">Bắt đầu</button>
                    </form>
                @else
                    <span class="muted">Chưa đủ câu</span>
                @endif
            </div>
        @endforeach
    @endif
@endsection
