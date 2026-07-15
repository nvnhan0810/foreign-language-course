@extends('user.layout')

@section('title', 'Vocabulary — FLC')
@section('heading', 'Vocabulary')

@section('content')
    @if ($items->isEmpty())
        <div class="empty-state">
            No words yet. Look up a word and tap Save.
        </div>
    @else
        <div class="form-group" style="margin-top:0">
            <div class="input-with-clear">
                <input
                    type="search"
                    id="vocab-search"
                    class="form-control"
                    placeholder="Search saved words..."
                    autocomplete="off"
                    enterkeyhint="search"
                >
                <button type="button" class="input-clear" hidden aria-label="Clear">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" aria-hidden="true">
                        <path fill="currentColor" d="M19 6.41 17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
                    </svg>
                </button>
            </div>
        </div>
        <div data-vocab-list>
            @foreach ($items as $vocab)
                @php
                    $meanings = is_array($vocab->meanings) ? $vocab->meanings : [];
                    $firstDef = $meanings[0]['definition'] ?? '';
                    $searchText = strtolower(trim($vocab->word.' '.$firstDef.' '.($vocab->phonetic ?? '')));
                @endphp
                <div class="card vocab-card" data-vocab-search="{{ $searchText }}">
                    <div class="vocab-card-row">
                        <a href="{{ route('user.home.vocab.show', $vocab->id) }}" class="vocab-card-link">
                            <p class="card-title">{{ $vocab->word }}</p>
                            @if ($vocab->phonetic)
                                <p class="card-subtitle vocab-card-phonetic">{{ $vocab->phonetic }}</p>
                            @endif
                            @if ($firstDef)
                                <p class="vocab-card-preview">{{ Str::limit($firstDef, 100) }}</p>
                            @endif
                            <span class="vocab-card-hint">View details ›</span>
                        </a>
                        <div class="vocab-card-actions">
                            <button
                                type="button"
                                class="btn-icon flc-pronounce"
                                data-pronounce-url="{{ route('user.home.dictionary.pronounce', $vocab->word) }}"
                                data-word="{{ $vocab->word }}"
                                title="Play pronunciation"
                                aria-label="Play pronunciation"
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
                </div>
            @endforeach
        </div>
        <div class="empty-state vocab-search-empty" hidden>
            No matching words found.
        </div>
    @endif
@endsection
