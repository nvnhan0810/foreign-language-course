{{-- Expects: $meanings (list), optional $synonyms / $antonyms (list), optional $extraExamples --}}
@php
    $meanings = is_array($meanings ?? null) ? $meanings : [];
    $synonyms = collect(is_array($synonyms ?? null) ? $synonyms : [])
        ->merge(collect($meanings)->flatMap(fn ($m) => is_array($m['synonyms'] ?? null) ? $m['synonyms'] : []))
        ->filter(fn ($w) => is_string($w) && trim($w) !== '')
        ->map(fn ($w) => trim($w))
        ->unique()
        ->values()
        ->all();
    $antonyms = collect(is_array($antonyms ?? null) ? $antonyms : [])
        ->merge(collect($meanings)->flatMap(fn ($m) => is_array($m['antonyms'] ?? null) ? $m['antonyms'] : []))
        ->filter(fn ($w) => is_string($w) && trim($w) !== '')
        ->map(fn ($w) => trim($w))
        ->unique()
        ->values()
        ->all();
    $tabId = 'dict-tabs-' . uniqid();
@endphp

<div class="dict-entry" data-dict-entry>
    <div class="dict-tabs" role="tablist" aria-label="Dictionary sections">
        <button type="button" class="dict-tab active" role="tab" aria-selected="true" data-dict-tab="meanings" aria-controls="{{ $tabId }}-meanings">Meanings</button>
        <button type="button" class="dict-tab" role="tab" aria-selected="false" data-dict-tab="synonyms" aria-controls="{{ $tabId }}-synonyms">Synonyms</button>
        <button type="button" class="dict-tab" role="tab" aria-selected="false" data-dict-tab="antonyms" aria-controls="{{ $tabId }}-antonyms">Antonyms</button>
    </div>

    <div id="{{ $tabId }}-meanings" class="dict-panel active" role="tabpanel" data-dict-panel="meanings">
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
            <p class="muted">No detailed definitions yet.</p>
        @endif

        @if (!empty($extraExamples) && count($extraExamples) > 0)
            <h3 style="font-size:15px;margin:20px 0 10px">More examples</h3>
            @foreach ($extraExamples as $example)
                <p class="muted" style="font-style:italic;margin:0 0 8px">"{{ is_object($example) ? $example->example : $example }}"</p>
            @endforeach
        @endif
    </div>

    <div id="{{ $tabId }}-synonyms" class="dict-panel" role="tabpanel" data-dict-panel="synonyms" hidden>
        @if (count($synonyms) > 0)
            <div class="related-words">
                @foreach ($synonyms as $word)
                    <span class="related-word">{{ $word }}</span>
                @endforeach
            </div>
        @else
            <p class="muted">No synonyms found.</p>
        @endif
    </div>

    <div id="{{ $tabId }}-antonyms" class="dict-panel" role="tabpanel" data-dict-panel="antonyms" hidden>
        @if (count($antonyms) > 0)
            <div class="related-words">
                @foreach ($antonyms as $word)
                    <span class="related-word">{{ $word }}</span>
                @endforeach
            </div>
        @else
            <p class="muted">No antonyms found.</p>
        @endif
    </div>
</div>
