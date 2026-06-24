@extends('user.layout')

@section('title', 'Tra từ — FLC')
@section('heading', 'Tra từ')

@section('content')
    <p class="muted" style="margin-top:0;font-weight:600">Tra từ Anh–Anh (giống app)</p>

    <form action="{{ route('user.home.lookup.search') }}" method="POST">
        @csrf
        <div class="form-group">
            <input
                type="text"
                name="word"
                class="form-control"
                placeholder="Nhập từ hoặc dán vào đây..."
                value="{{ old('word', $word) }}"
                autofocus
            >
        </div>
        <button type="submit" class="btn btn-block">Tra từ</button>
    </form>

    @if ($result)
        <div class="card" style="margin-top:20px">
            <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
                <p class="card-title" style="margin:0">{{ $result['word'] ?? '' }}</p>
                @if (!empty($result['audio_url']))
                    <button
                        type="button"
                        class="btn btn-secondary btn-sm flc-pronounce"
                        data-audio="{{ $result['audio_url'] }}"
                        data-word="{{ $result['word'] ?? '' }}"
                        title="Nghe phát âm"
                    >🔊</button>
                @endif
            </div>
            @if (!empty($result['phonetic']))
                <p class="card-subtitle" style="font-style:italic;margin-top:6px">{{ $result['phonetic'] }}</p>
            @endif

            @foreach ($result['meanings'] ?? [] as $meaning)
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
        </div>

        @unless ($saved)
            <form action="{{ route('user.home.lookup.save') }}" method="POST" style="margin-top:12px">
                @csrf
                <input type="hidden" name="word" value="{{ $result['word'] ?? '' }}">
                <input type="hidden" name="phonetic" value="{{ $result['phonetic'] ?? '' }}">
                @foreach ($result['meanings'] ?? [] as $i => $meaning)
                    @foreach ($meaning as $key => $value)
                        <input type="hidden" name="meanings[{{ $i }}][{{ $key }}]" value="{{ $value }}">
                    @endforeach
                @endforeach
                <button type="submit" class="btn btn-secondary btn-block">Lưu từ</button>
            </form>
        @else
            <p class="muted" style="text-align:center;margin-top:12px">Đã lưu từ</p>
        @endunless
    @endif

    <script>
        document.querySelectorAll('.flc-pronounce').forEach((btn) => {
            btn.addEventListener('click', () => {
                const audioUrl = btn.dataset.audio;
                const word = btn.dataset.word || '';
                if (audioUrl) {
                    new Audio(audioUrl).play().catch(() => speakWord(word));
                    return;
                }
                speakWord(word);
            });
        });

        function speakWord(word) {
            if (!word || !('speechSynthesis' in window)) return;
            const utterance = new SpeechSynthesisUtterance(word);
            utterance.lang = 'en-US';
            speechSynthesis.speak(utterance);
        }
    </script>
@endsection
