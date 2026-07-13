<?php

namespace Flc\Dictionary\Application\Handler;

use App\Models\DictionaryEntry;
use Flc\Dictionary\Application\Command\UpsertDictionaryOnSave;
use Flc\Dictionary\Application\FreeDictionaryGateway;
use Flc\Dictionary\Domain\DictionaryEntryAggregate;
use Flc\Shared\Application\AggregateRepository;
use Flc\Shared\Application\Command;
use Flc\Shared\Application\CommandHandler;
use Illuminate\Support\Str;

final class UpsertDictionaryOnSaveHandler implements CommandHandler
{
    public function __construct(
        private readonly AggregateRepository $aggregates,
        private readonly FreeDictionaryGateway $gateway,
    ) {}

    public function handle(Command $command): mixed
    {
        assert($command instanceof UpsertDictionaryOnSave);

        $normalized = Str::lower(trim($command->word));
        if ($normalized === '') {
            return null;
        }

        /** @var DictionaryEntryAggregate|null $aggregate */
        $aggregate = $this->aggregates->load(DictionaryEntryAggregate::class, $normalized);

        if ($aggregate === null || $aggregate->isDeleted()) {
            $aggregate = $this->bootstrapFromProjection($normalized);
        }

        $payload = $command->payload;

        if ($aggregate === null) {
            $payload ??= $this->gateway->fetch($normalized);
            if ($payload === null) {
                return null;
            }
            $aggregate = DictionaryEntryAggregate::createFromPayload($normalized, $payload);
            $this->aggregates->save($aggregate);

            return $aggregate;
        }

        $aggregate->recordSave($payload);
        $this->aggregates->save($aggregate);

        return $aggregate;
    }

    private function bootstrapFromProjection(string $normalized): ?DictionaryEntryAggregate
    {
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

        if (! $entry) {
            return null;
        }

        $meanings = [];
        foreach ($entry->meanings as $meaning) {
            $meanings[] = [
                'part_of_speech' => $meaning->part_of_speech,
                'definition' => $meaning->definition,
                'examples' => $meaning->examples->pluck('example')->all(),
                'synonyms' => $meaning->synonyms->pluck('term')->all(),
                'antonyms' => $meaning->antonyms->pluck('term')->all(),
            ];
        }

        $aggregate = DictionaryEntryAggregate::initializeFromReadModel($normalized, [
            'phonetic' => $entry->phonetic,
            'audio_url' => $entry->audio_url,
            'source' => $entry->source,
            'is_curated' => $entry->is_curated,
            'save_count' => $entry->save_count,
            'meanings' => $meanings,
            'synonyms' => $entry->synonyms->whereNull('dictionary_meaning_id')->pluck('term')->all(),
            'antonyms' => $entry->antonyms->whereNull('dictionary_meaning_id')->pluck('term')->all(),
        ]);

        $this->aggregates->save($aggregate);

        return $this->aggregates->load(DictionaryEntryAggregate::class, $normalized);
    }
}
