<?php

namespace Flc\Dictionary\Application\Handler;

use App\Models\DictionaryEntry;
use Flc\Dictionary\Application\FreeDictionaryGateway;
use Flc\Dictionary\Application\Query\LookupWord;
use Flc\Dictionary\Domain\DictionaryEntryAggregate;
use Flc\Shared\Application\AggregateRepository;
use Flc\Shared\Application\Query;
use Flc\Shared\Application\QueryHandler;
use Illuminate\Support\Str;

final class LookupWordHandler implements QueryHandler
{
    public function __construct(
        private readonly AggregateRepository $aggregates,
        private readonly FreeDictionaryGateway $gateway,
    ) {}

    public function handle(Query $query): mixed
    {
        assert($query instanceof LookupWord);

        $normalized = Str::lower(trim($query->word));
        if ($normalized === '') {
            return null;
        }

        /** @var DictionaryEntryAggregate|null $aggregate */
        $aggregate = $this->aggregates->load(DictionaryEntryAggregate::class, $normalized);
        if ($aggregate && ! $aggregate->isDeleted()) {
            return $aggregate->toClientPayload();
        }

        // Projection fallback (before events seeded)
        $entry = DictionaryEntry::query()
            ->where('word', $normalized)
            ->with([
                'meanings.examples',
                'meanings.synonyms',
                'meanings.antonyms',
                'synonyms',
                'antonyms',
            ])
            ->first();

        if ($entry) {
            return $this->projectionToPayload($entry);
        }

        return $this->gateway->fetch($normalized);
    }

    /**
     * @return array<string, mixed>
     */
    private function projectionToPayload(DictionaryEntry $entry): array
    {
        $meanings = [];
        foreach ($entry->meanings as $meaning) {
            $examples = $meaning->examples->pluck('example')->filter()->values()->all();
            $meanings[] = [
                'part_of_speech' => $meaning->part_of_speech,
                'definition' => $meaning->definition,
                'example' => $examples[0] ?? null,
                'examples' => $examples,
                'synonyms' => $meaning->synonyms->pluck('term')->values()->all(),
                'antonyms' => $meaning->antonyms->pluck('term')->values()->all(),
            ];
        }

        return [
            'word' => $entry->word,
            'phonetic' => $entry->phonetic,
            'audio_url' => $entry->audio_url,
            'meanings' => $meanings,
            'synonyms' => $entry->synonyms->whereNull('dictionary_meaning_id')->pluck('term')->values()->all(),
            'antonyms' => $entry->antonyms->whereNull('dictionary_meaning_id')->pluck('term')->values()->all(),
            'source' => 'flc',
            'curated' => (bool) $entry->is_curated,
        ];
    }
}
