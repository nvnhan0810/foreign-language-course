@extends('user.layout')

@section('title', $vocab->word . ' — FLC')
@section('heading', $vocab->word)
@section('back_url', route('user.home.vocab'))

@section('content')
    <div class="card">
        <div class="vocab-detail-header">
            <div class="vocab-detail-title">
                <p class="card-title" style="margin:0">{{ $vocab->word }}</p>
                @if ($vocab->phonetic)
                    <p class="card-subtitle" style="font-style:italic;margin-top:6px">{{ $vocab->phonetic }}</p>
                @endif
            </div>
            <div class="vocab-card-actions">
                <button
                    type="button"
                    class="btn-icon flc-pronounce"
                    data-pronounce-url="{{ route('user.home.dictionary.pronounce', $vocab->word) }}"
                    data-word="{{ $vocab->word }}"
                    title="Nghe phát âm"
                    aria-label="Nghe phát âm"
                >🔊</button>
                <div class="action-menu">
                    <button type="button" class="btn-icon action-menu-trigger" aria-label="Tùy chọn" aria-haspopup="true">⋮</button>
                    <div class="action-menu-panel" hidden>
                        <form action="{{ route('user.home.vocab.destroy', $vocab) }}" method="POST" class="flc-form-submit"
                              onsubmit="return confirm('Xóa &quot;{{ $vocab->word }}&quot; khỏi danh sách?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="action-menu-danger">Xóa</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        @php
            $meanings = is_array($vocab->meanings) ? $vocab->meanings : [];
        @endphp

        @if (count($meanings) > 0)
            @foreach ($meanings as $meaning)
                <div class="meaning-block">
                    @if (!empty($meaning['part_of_speech']))
                        <span class="pos-tag">{{ $meaning['part_of_speech'] }}</span>
                    @endif
                    <p style="margin:4px 0">{{ $meaning['definition'] ?? '' }}</p>
                    @if (!empty($meaning['example']))
                        <p class="muted" style="font-style:italic;margin:4px 0">"{{ $meaning['example'] }}"</p>
                    @endif
                </div>
            @endforeach
        @else
            <p class="muted">Chưa có định nghĩa chi tiết.</p>
        @endif

        @if ($vocab->examples->isNotEmpty())
            <h3 style="font-size:15px;margin:20px 0 10px">Ví dụ thêm</h3>
            @foreach ($vocab->examples as $example)
                <p class="muted" style="font-style:italic;margin:0 0 8px">"{{ $example->example }}"</p>
            @endforeach
        @endif
    </div>
@endsection
