@extends('user.layout')

@section('title', 'Từ vựng — FLC')
@section('heading', 'Từ vựng')

@section('content')
    @if ($items->isEmpty())
        <div class="empty-state">
            Chưa có từ nào. Tra từ và bấm Lưu.
        </div>
    @else
        @foreach ($items as $vocab)
            @php
                $meanings = is_array($vocab->meanings) ? $vocab->meanings : [];
                $firstDef = $meanings[0]['definition'] ?? '';
            @endphp
            <div class="card vocab-card">
                <div class="vocab-card-row">
                    <a href="{{ route('user.home.vocab.show', $vocab->id) }}" class="vocab-card-link">
                        <p class="card-title">{{ $vocab->word }}</p>
                        @if ($vocab->phonetic)
                            <p class="card-subtitle vocab-card-phonetic">{{ $vocab->phonetic }}</p>
                        @endif
                        @if ($firstDef)
                            <p class="vocab-card-preview">{{ Str::limit($firstDef, 100) }}</p>
                        @endif
                        <span class="vocab-card-hint">Xem chi tiết ›</span>
                    </a>
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
                                <form action="{{ route('user.home.vocab.destroy', $vocab->id) }}" method="POST" class="flc-form-submit"
                                      onsubmit="return confirm('Xóa &quot;{{ $vocab->word }}&quot; khỏi danh sách?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="action-menu-danger">Xóa</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    @endif
@endsection
