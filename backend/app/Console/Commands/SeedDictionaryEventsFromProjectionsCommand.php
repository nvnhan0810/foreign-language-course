<?php

namespace App\Console\Commands;

use App\Models\DictionaryEntry;
use Flc\Dictionary\Domain\DictionaryEntryAggregate;
use Flc\Shared\Application\AggregateRepository;
use Flc\Shared\Application\EventStore;
use Illuminate\Console\Command;

class SeedDictionaryEventsFromProjectionsCommand extends Command
{
    protected $signature = 'flc:seed-dictionary-events {--force : Re-seed even if stream exists}';

    protected $description = 'Bootstrap dictionary event streams from projection tables (cut-over)';

    public function handle(EventStore $store, AggregateRepository $aggregates): int
    {
        $count = 0;
        $skipped = 0;

        DictionaryEntry::query()
            ->with([
                'meanings.examples',
                'meanings.synonyms',
                'meanings.antonyms',
                'synonyms',
                'antonyms',
            ])
            ->orderBy('id')
            ->chunkById(50, function ($entries) use ($store, $aggregates, &$count, &$skipped) {
                foreach ($entries as $entry) {
                    $type = DictionaryEntryAggregate::aggregateType();
                    if ($store->exists($type, $entry->word) && ! $this->option('force')) {
                        $skipped++;

                        continue;
                    }

                    if ($store->exists($type, $entry->word) && $this->option('force')) {
                        $this->warn("Skip force re-seed for {$entry->word} (delete stream manually if needed)");
                        $skipped++;

                        continue;
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

                    $aggregate = DictionaryEntryAggregate::initializeFromReadModel($entry->word, [
                        'phonetic' => $entry->phonetic,
                        'audio_url' => $entry->audio_url,
                        'source' => $entry->source,
                        'is_curated' => $entry->is_curated,
                        'save_count' => $entry->save_count,
                        'meanings' => $meanings,
                        'synonyms' => $entry->synonyms->whereNull('dictionary_meaning_id')->pluck('term')->all(),
                        'antonyms' => $entry->antonyms->whereNull('dictionary_meaning_id')->pluck('term')->all(),
                    ]);

                    $aggregates->save($aggregate);
                    $count++;
                    $this->line("Seeded {$entry->word}");
                }
            });

        $this->info("Done. seeded={$count} skipped={$skipped}");

        return self::SUCCESS;
    }
}
