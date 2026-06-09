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
            <div class="card">
                <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px">
                    <div>
                        <p class="card-title">{{ $vocab->word }}</p>
                        @if ($vocab->phonetic)
                            <p class="card-subtitle" style="font-style:italic">{{ $vocab->phonetic }}</p>
                        @endif
                        @php
                            $meanings = is_array($vocab->meanings) ? $vocab->meanings : [];
                            $firstDef = $meanings[0]['definition'] ?? '';
                        @endphp
                        @if ($firstDef)
                            <p style="margin:8px 0 0;font-size:14px">{{ Str::limit($firstDef, 120) }}</p>
                        @endif
                    </div>
                    <form action="{{ route('user.home.vocab.destroy', $vocab) }}" method="POST" class="inline-form"
                          onsubmit="return confirm('Xóa &quot;{{ $vocab->word }}&quot; khỏi danh sách?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-secondary btn-sm">Xóa</button>
                    </form>
                </div>
            </div>
        @endforeach
    @endif
@endsection
