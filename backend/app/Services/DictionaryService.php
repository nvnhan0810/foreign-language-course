<?php

namespace App\Services;

use App\Models\DictionaryEntry;
use Flc\Dictionary\Application\Command\CurateDictionaryEntry;
use Flc\Dictionary\Application\Command\UpsertDictionaryOnSave;
use Flc\Dictionary\Application\Query\LookupWord;
use Flc\Dictionary\Domain\DictionaryEntryAggregate;
use Flc\Shared\Application\CommandBus;
use Flc\Shared\Application\QueryBus;
use Illuminate\Support\Str;

/**
 * Compatibility facade over Flc Dictionary CQRS handlers.
 * Prefer injecting CommandBus / QueryBus in new code.
 */
class DictionaryService
{
    public function __construct(
        private readonly QueryBus $queries,
        private readonly CommandBus $commands,
    ) {}

    /**
     * @return array<string, mixed>|null
     */
    public function lookup(string $word): ?array
    {
        return $this->queries->ask(new LookupWord($word));
    }

    /**
     * @param  array<string, mixed>|null  $payload
     */
    public function upsertOnSave(string $word, ?array $payload = null): ?DictionaryEntry
    {
        $aggregate = $this->commands->dispatch(new UpsertDictionaryOnSave($word, $payload));

        if (! $aggregate instanceof DictionaryEntryAggregate) {
            return null;
        }

        return DictionaryEntry::query()->where('word', $aggregate->aggregateId())->first();
    }

    /**
     * @param  list<array<string, mixed>>  $meanings
     * @return list<array<string, mixed>>
     */
    public function meaningsForVocabulary(array $meanings): array
    {
        $out = [];

        foreach ($meanings as $meaning) {
            if (! is_array($meaning)) {
                continue;
            }

            $examples = [];
            if (! empty($meaning['examples']) && is_array($meaning['examples'])) {
                foreach ($meaning['examples'] as $example) {
                    if (is_string($example) && trim($example) !== '') {
                        $examples[] = trim($example);
                    }
                }
            } elseif (! empty($meaning['example']) && is_string($meaning['example'])) {
                $examples[] = $meaning['example'];
            }

            $out[] = [
                'part_of_speech' => $meaning['part_of_speech'] ?? null,
                'definition' => $meaning['definition'] ?? '',
                'example' => $examples[0] ?? null,
            ];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function replaceCuratedContent(DictionaryEntry $entry, array $data): DictionaryEntry
    {
        $word = Str::lower(trim((string) ($data['word'] ?? $entry->word)));
        $this->commands->dispatch(new CurateDictionaryEntry($word, $data));

        return DictionaryEntry::query()
            ->where('word', $word)
            ->with(['meanings.examples', 'meanings.synonyms', 'meanings.antonyms', 'synonyms', 'antonyms'])
            ->firstOrFail();
    }
}
