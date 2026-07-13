<?php

namespace Flc\Dictionary\Infrastructure\Projection;

use App\Models\DictionaryAntonym;
use App\Models\DictionaryEntry;
use App\Models\DictionaryExample;
use App\Models\DictionaryMeaning;
use App\Models\DictionarySynonym;
use Flc\Dictionary\Domain\Event\DictionaryContentMerged;
use Flc\Dictionary\Domain\Event\DictionaryContentReplaced;
use Flc\Dictionary\Domain\Event\DictionaryEntryCreated;
use Flc\Dictionary\Domain\Event\DictionaryEntryDeleted;
use Flc\Dictionary\Domain\Event\DictionarySaveCounted;
use Flc\Shared\Application\Projector;
use Flc\Shared\Domain\DomainEvent;
use Illuminate\Support\Facades\DB;

final class DictionaryEntryProjector implements Projector
{
    public function subscribedEvents(): array
    {
        return [
            DictionaryEntryCreated::class,
            DictionarySaveCounted::class,
            DictionaryContentReplaced::class,
            DictionaryContentMerged::class,
            DictionaryEntryDeleted::class,
        ];
    }

    public function handle(DomainEvent $event): void
    {
        match ($event::class) {
            DictionaryEntryCreated::class => $this->onCreated($event),
            DictionarySaveCounted::class => $this->onSaveCounted($event),
            DictionaryContentReplaced::class => $this->onContentReplaced($event),
            DictionaryContentMerged::class => $this->onContentMerged($event),
            DictionaryEntryDeleted::class => $this->onDeleted($event),
            default => null,
        };
    }

    private function onCreated(DictionaryEntryCreated $event): void
    {
        $p = $event->payload;
        DictionaryEntry::query()->updateOrCreate(
            ['word' => $p['word']],
            [
                'phonetic' => $p['phonetic'] ?? null,
                'audio_url' => $p['audio_url'] ?? null,
                'source' => $p['source'] ?? 'user_save',
                'is_curated' => false,
                'save_count' => (int) ($p['save_count'] ?? 1),
            ]
        );
    }

    private function onSaveCounted(DictionarySaveCounted $event): void
    {
        DictionaryEntry::query()
            ->where('word', $event->payload['word'])
            ->update(['save_count' => (int) $event->payload['save_count']]);
    }

    private function onContentReplaced(DictionaryContentReplaced $event): void
    {
        DB::transaction(function () use ($event) {
            $p = $event->payload;
            $entry = DictionaryEntry::query()->updateOrCreate(
                ['word' => $p['word']],
                [
                    'phonetic' => $p['phonetic'] ?? null,
                    'audio_url' => $p['audio_url'] ?? null,
                    'source' => $p['source'] ?? 'admin',
                    'is_curated' => (bool) ($p['is_curated'] ?? false),
                ]
            );

            $this->replaceChildren($entry, $p['meanings'] ?? [], $p['synonyms'] ?? [], $p['antonyms'] ?? []);
        });
    }

    private function onContentMerged(DictionaryContentMerged $event): void
    {
        DB::transaction(function () use ($event) {
            $p = $event->payload;
            $entry = DictionaryEntry::query()->where('word', $p['word'])->first();
            if (! $entry) {
                return;
            }

            $entry->update([
                'phonetic' => $p['phonetic'] ?? null,
                'audio_url' => $p['audio_url'] ?? null,
                'save_count' => (int) $p['save_count'],
            ]);

            $this->replaceChildren($entry, $p['meanings'] ?? [], $p['synonyms'] ?? [], $p['antonyms'] ?? []);
        });
    }

    private function onDeleted(DictionaryEntryDeleted $event): void
    {
        DictionaryEntry::query()->where('word', $event->payload['word'])->delete();
    }

    /**
     * @param  list<array<string, mixed>>  $meanings
     * @param  list<string>  $synonyms
     * @param  list<string>  $antonyms
     */
    private function replaceChildren(DictionaryEntry $entry, array $meanings, array $synonyms, array $antonyms): void
    {
        $entry->meanings()->each(function (DictionaryMeaning $meaning) {
            $meaning->examples()->delete();
            $meaning->synonyms()->delete();
            $meaning->antonyms()->delete();
            $meaning->delete();
        });
        $entry->synonyms()->delete();
        $entry->antonyms()->delete();

        foreach (array_values($meanings) as $index => $meaning) {
            if (! is_array($meaning)) {
                continue;
            }
            $definition = trim((string) ($meaning['definition'] ?? ''));
            if ($definition === '') {
                continue;
            }

            $row = DictionaryMeaning::query()->create([
                'dictionary_entry_id' => $entry->id,
                'part_of_speech' => $meaning['part_of_speech'] ?? null,
                'definition' => $definition,
                'position' => $index,
            ]);

            foreach (array_values($this->stringList($meaning['examples'] ?? [])) as $exIndex => $example) {
                DictionaryExample::query()->create([
                    'dictionary_meaning_id' => $row->id,
                    'example' => $example,
                    'position' => $exIndex,
                ]);
            }

            foreach (array_values($this->stringList($meaning['synonyms'] ?? [])) as $synIndex => $term) {
                DictionarySynonym::query()->create([
                    'dictionary_entry_id' => $entry->id,
                    'dictionary_meaning_id' => $row->id,
                    'term' => $term,
                    'position' => $synIndex,
                ]);
            }

            foreach (array_values($this->stringList($meaning['antonyms'] ?? [])) as $antIndex => $term) {
                DictionaryAntonym::query()->create([
                    'dictionary_entry_id' => $entry->id,
                    'dictionary_meaning_id' => $row->id,
                    'term' => $term,
                    'position' => $antIndex,
                ]);
            }
        }

        foreach (array_values($this->stringList($synonyms)) as $index => $term) {
            DictionarySynonym::query()->create([
                'dictionary_entry_id' => $entry->id,
                'dictionary_meaning_id' => null,
                'term' => $term,
                'position' => $index,
            ]);
        }

        foreach (array_values($this->stringList($antonyms)) as $index => $term) {
            DictionaryAntonym::query()->create([
                'dictionary_entry_id' => $entry->id,
                'dictionary_meaning_id' => null,
                'term' => $term,
                'position' => $index,
            ]);
        }
    }

    /**
     * @param  mixed  $values
     * @return list<string>
     */
    private function stringList(mixed $values): array
    {
        if (! is_array($values)) {
            return [];
        }
        $out = [];
        foreach ($values as $value) {
            if (is_string($value) && trim($value) !== '') {
                $out[] = trim($value);
            }
        }

        return array_values(array_unique($out));
    }
}
