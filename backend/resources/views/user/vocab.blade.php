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
                    <div style="display:flex;align-items:flex-start;gap:8px;flex:1;min-width:0">
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
                        <button
                            type="button"
                            class="btn btn-secondary btn-sm flc-pronounce"
                            data-pronounce-url="{{ route('user.home.dictionary.pronounce', $vocab->word) }}"
                            data-word="{{ $vocab->word }}"
                            title="Nghe phát âm"
                            style="flex-shrink:0"
                        >🔊</button>
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

    <script>
        document.querySelectorAll('.flc-pronounce').forEach((btn) => {
            btn.addEventListener('click', async () => {
                const pronounceUrl = btn.dataset.pronounceUrl;
                const word = btn.dataset.word || '';
                if (pronounceUrl) {
                    try {
                        const res = await fetch(pronounceUrl, {
                            headers: { Accept: 'application/json' },
                        });
                        if (res.ok) {
                            const data = await res.json();
                            if (data.audio_url) {
                                await new Audio(data.audio_url).play();
                                return;
                            }
                        }
                    } catch (_) {}
                }
                if (word && 'speechSynthesis' in window) {
                    const utterance = new SpeechSynthesisUtterance(word);
                    utterance.lang = 'en-US';
                    speechSynthesis.speak(utterance);
                }
            });
        });
    </script>
@endsection
