{{-- Expects: $meanings (list), optional $synonyms / $antonyms (entry-level), optional $extraExamples, optional $preferDetail --}}
@php
    $meanings = is_array($meanings ?? null) ? $meanings : [];
    $preferDetail = (bool) ($preferDetail ?? true);
    $entrySynonyms = collect(is_array($synonyms ?? null) ? $synonyms : [])
        ->filter(fn ($w) => is_string($w) && trim($w) !== '')
        ->map(fn ($w) => trim($w))
        ->unique()
        ->values()
        ->all();
    $entryAntonyms = collect(is_array($antonyms ?? null) ? $antonyms : [])
        ->filter(fn ($w) => is_string($w) && trim($w) !== '')
        ->map(fn ($w) => trim($w))
        ->unique()
        ->values()
        ->all();
    $relatedWordUrl = function (string $related) use ($preferDetail) {
        return route('user.home.word.open', ['word' => $related, 'detail' => $preferDetail ? 1 : 0]);
    };
    $moreExamples = collect(is_array($extraExamples ?? null) ? $extraExamples : [])
        ->map(function ($example) {
            if (is_object($example)) {
                return trim((string) ($example->example ?? ''));
            }
            if (is_array($example)) {
                return trim((string) ($example['example'] ?? ''));
            }

            return is_string($example) ? trim($example) : '';
        })
        ->filter(fn (string $text) => $text !== '');
    foreach ($meanings as $meaning) {
        $meaningExamples = collect(is_array($meaning['examples'] ?? null) ? $meaning['examples'] : [])
            ->filter(fn ($text) => is_string($text) && trim($text) !== '')
            ->map(fn (string $text) => trim($text))
            ->values();
        if ($meaningExamples->isEmpty() && ! empty($meaning['example']) && is_string($meaning['example'])) {
            $meaningExamples = collect([trim($meaning['example'])]);
        }
        $moreExamples = $moreExamples->merge($meaningExamples->slice(1));
    }
    $moreExamples = $moreExamples->unique()->values();
@endphp

<div class="dict-entry" data-dict-entry>
    @if (count($meanings) > 0)
        @foreach ($meanings as $index => $meaning)
            @php
                $meaningSynonyms = collect(is_array($meaning['synonyms'] ?? null) ? $meaning['synonyms'] : [])
                    ->filter(fn ($w) => is_string($w) && trim($w) !== '')
                    ->map(fn ($w) => trim($w));
                $meaningAntonyms = collect(is_array($meaning['antonyms'] ?? null) ? $meaning['antonyms'] : [])
                    ->filter(fn ($w) => is_string($w) && trim($w) !== '')
                    ->map(fn ($w) => trim($w));
                if ($index === 0) {
                    $meaningSynonyms = $meaningSynonyms->merge($entrySynonyms)->unique()->values();
                    $meaningAntonyms = $meaningAntonyms->merge($entryAntonyms)->unique()->values();
                } else {
                    $meaningSynonyms = $meaningSynonyms->unique()->values();
                    $meaningAntonyms = $meaningAntonyms->unique()->values();
                }
            @endphp
            <div class="meaning-block">
                @if (!empty($meaning['part_of_speech']))
                    <span class="pos-tag">{{ $meaning['part_of_speech'] }}</span>
                @endif
                <p style="margin:4px 0">{{ $meaning['definition'] ?? '' }}</p>
                @if (!empty($meaning['example']))
                    <p class="muted" style="font-style:italic;margin:4px 0">"{{ $meaning['example'] }}"</p>
                @endif

                @if ($meaningSynonyms->isNotEmpty())
                    <div class="related-group">
                        <div class="related-label">Synonyms</div>
                        <div class="related-words">
                            @foreach ($meaningSynonyms as $related)
                                <a href="{{ $relatedWordUrl($related) }}" class="related-word">{{ $related }}</a>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if ($meaningAntonyms->isNotEmpty())
                    <div class="related-group">
                        <div class="related-label">Antonyms</div>
                        <div class="related-words">
                            @foreach ($meaningAntonyms as $related)
                                <a href="{{ $relatedWordUrl($related) }}" class="related-word">{{ $related }}</a>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        @endforeach
    @else
        <p class="muted">No detailed definitions yet.</p>
        @if (count($entrySynonyms) > 0 || count($entryAntonyms) > 0)
            @if (count($entrySynonyms) > 0)
                <div class="related-group">
                    <div class="related-label">Synonyms</div>
                    <div class="related-words">
                        @foreach ($entrySynonyms as $related)
                            <a href="{{ $relatedWordUrl($related) }}" class="related-word">{{ $related }}</a>
                        @endforeach
                    </div>
                </div>
            @endif
            @if (count($entryAntonyms) > 0)
                <div class="related-group">
                    <div class="related-label">Antonyms</div>
                    <div class="related-words">
                        @foreach ($entryAntonyms as $related)
                            <a href="{{ $relatedWordUrl($related) }}" class="related-word">{{ $related }}</a>
                        @endforeach
                    </div>
                </div>
            @endif
        @endif
    @endif

    @if ($moreExamples->isNotEmpty())
        <h3 style="font-size:15px;margin:20px 0 10px">More examples</h3>
        @foreach ($moreExamples as $example)
            <p class="muted" style="font-style:italic;margin:0 0 8px">"{{ $example }}"</p>
        @endforeach
    @endif
</div>
