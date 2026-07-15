@extends('user.layout')

@section('title', 'Lookup — FLC')
@section('heading', 'Lookup')

@section('content')
    <p class="muted" style="margin-top:0;font-weight:600">English dictionary lookup</p>

    <form action="{{ route('user.home.lookup.search') }}" method="POST" class="flc-form-submit">
        @csrf
        <div class="form-group">
            <div class="input-with-clear">
                <input
                    type="text"
                    name="word"
                    class="form-control"
                    placeholder="Type or paste a word..."
                    value="{{ old('word', $word) }}"
                    autofocus
                >
                <button type="button" class="input-clear" hidden aria-label="Clear">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" aria-hidden="true">
                        <path fill="currentColor" d="M19 6.41 17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
                    </svg>
                </button>
            </div>
        </div>
        <button type="submit" class="btn btn-block">Look up</button>
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
                        title="Pronounce"
                    >🔊</button>
                @endif
            </div>
            @if (!empty($result['phonetic']))
                <p class="card-subtitle" style="font-style:italic;margin-top:6px">{{ $result['phonetic'] }}</p>
            @endif

            @include('user.partials.dictionary-entry-tabs', [
                'meanings' => $result['meanings'] ?? [],
                'synonyms' => $result['synonyms'] ?? [],
                'antonyms' => $result['antonyms'] ?? [],
                'preferDetail' => false,
            ])
        </div>

        @unless ($saved)
            <form action="{{ route('user.home.lookup.save') }}" method="POST" style="margin-top:12px" class="flc-form-submit">
                @csrf
                <input type="hidden" name="word" value="{{ $result['word'] ?? '' }}">
                <input type="hidden" name="phonetic" value="{{ $result['phonetic'] ?? '' }}">
                @foreach ($result['meanings'] ?? [] as $i => $meaning)
                    @php
                        $example = $meaning['example']
                            ?? (is_array($meaning['examples'] ?? null) ? ($meaning['examples'][0] ?? '') : '');
                        $synonyms = $meaning['synonyms'] ?? [];
                        $antonyms = $meaning['antonyms'] ?? [];
                        if ($i === 0) {
                            $synonyms = collect(is_array($synonyms) ? $synonyms : [])
                                ->merge($result['synonyms'] ?? [])
                                ->filter(fn ($w) => is_string($w) && trim($w) !== '')
                                ->unique()
                                ->values()
                                ->all();
                            $antonyms = collect(is_array($antonyms) ? $antonyms : [])
                                ->merge($result['antonyms'] ?? [])
                                ->filter(fn ($w) => is_string($w) && trim($w) !== '')
                                ->unique()
                                ->values()
                                ->all();
                        }
                    @endphp
                    <input type="hidden" name="meanings[{{ $i }}][part_of_speech]" value="{{ $meaning['part_of_speech'] ?? '' }}">
                    <input type="hidden" name="meanings[{{ $i }}][definition]" value="{{ $meaning['definition'] ?? '' }}">
                    <input type="hidden" name="meanings[{{ $i }}][example]" value="{{ $example }}">
                    @foreach ($synonyms as $syn)
                        <input type="hidden" name="meanings[{{ $i }}][synonyms][]" value="{{ $syn }}">
                    @endforeach
                    @foreach ($antonyms as $ant)
                        <input type="hidden" name="meanings[{{ $i }}][antonyms][]" value="{{ $ant }}">
                    @endforeach
                @endforeach
                <button type="submit" class="btn btn-secondary btn-block">Save word</button>
            </form>
        @else
            <p class="muted" style="text-align:center;margin-top:12px">
                Saved
                @if (!empty($savedVocabularyId))
                    · <a href="{{ route('user.home.vocab.show', $savedVocabularyId) }}">View detail</a>
                @endif
            </p>
        @endunless
    @endif
@endsection
