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
                    title="Pronounce"
                    aria-label="Pronounce"
                >🔊</button>
                <div class="action-menu">
                    <button type="button" class="btn-icon action-menu-trigger" aria-label="Options" aria-haspopup="true">⋮</button>
                    <div class="action-menu-panel" hidden>
                        <form action="{{ route('user.home.vocab.destroy', $vocab->id) }}" method="POST" class="flc-form-submit"
                              onsubmit="return confirm('Remove &quot;{{ $vocab->word }}&quot; from your list?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="action-menu-danger">Delete</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        @include('user.partials.dictionary-entry-tabs', [
            'meanings' => is_array($vocab->meanings) ? $vocab->meanings : [],
            'synonyms' => [],
            'antonyms' => [],
            'extraExamples' => $vocab->examples,
            'preferDetail' => true,
        ])
    </div>
@endsection
