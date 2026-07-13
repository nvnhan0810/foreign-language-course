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
@endphp

<div class="dict-entry">
    <section class="dict-section">
        <h3 class="dict-section-title">Meanings</h3>
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
            <h4 class="dict-section-subtitle">More examples</h4>
            @foreach ($extraExamples as $example)
                <p class="muted" style="font-style:italic;margin:0 0 8px">"{{ is_object($example) ? $example->example : $example }}"</p>
            @endforeach
        @endif
    </section>

    <section class="dict-section">
        <h3 class="dict-section-title">Synonyms</h3>
        @if (count($synonyms) > 0)
            <div class="related-words">
                @foreach ($synonyms as $word)
                    <span class="related-word">{{ $word }}</span>
                @endforeach
            </div>
        @else
            <p class="muted">No synonyms found.</p>
        @endif
    </section>

    <section class="dict-section">
        <h3 class="dict-section-title">Antonyms</h3>
        @if (count($antonyms) > 0)
            <div class="related-words">
                @foreach ($antonyms as $word)
                    <span class="related-word">{{ $word }}</span>
                @endforeach
            </div>
        @else
            <p class="muted">No antonyms found.</p>
        @endif
    </section>
</div>
