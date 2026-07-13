<?php

namespace Flc\Dictionary\Domain;

use DomainException;
use Flc\Dictionary\Domain\Event\DictionaryContentMerged;
use Flc\Dictionary\Domain\Event\DictionaryContentReplaced;
use Flc\Dictionary\Domain\Event\DictionaryEntryCreated;
use Flc\Dictionary\Domain\Event\DictionaryEntryDeleted;
use Flc\Dictionary\Domain\Event\DictionarySaveCounted;
use Flc\Shared\Domain\AggregateRoot;
use Illuminate\Support\Str;

final class DictionaryEntryAggregate extends AggregateRoot
{
    private string $word = '';

    private ?string $phonetic = null;

    private ?string $audioUrl = null;

    private string $source = 'user_save';

    private bool $isCurated = false;

    private int $saveCount = 0;

    private bool $deleted = false;

    /** @var list<array<string, mixed>> */
    private array $meanings = [];

    /** @var list<string> */
    private array $synonyms = [];

    /** @var list<string> */
    private array $antonyms = [];

    public static function aggregateType(): string
    {
        return 'dictionary_entry';
    }

    public static function createFromPayload(string $word, array $payload): self
    {
        $normalized = Str::lower(trim($word));
        $aggregate = new self($normalized);
        $aggregate->recordThat(DictionaryEntryCreated::make(
            $normalized,
            $payload['phonetic'] ?? null,
            $payload['audio_url'] ?? null,
            $payload['source'] ?? 'user_save',
            1,
        ));

        $meanings = is_array($payload['meanings'] ?? null) ? $payload['meanings'] : [];
        $synonyms = self::stringList($payload['synonyms'] ?? []);
        $antonyms = self::stringList($payload['antonyms'] ?? []);

        if ($meanings !== [] || $synonyms !== [] || $antonyms !== []) {
            $aggregate->recordThat(DictionaryContentReplaced::make(
                $normalized,
                $payload['phonetic'] ?? null,
                $payload['audio_url'] ?? null,
                $payload['source'] ?? 'user_save',
                false,
                self::normalizeMeanings($meanings),
                $synonyms,
                $antonyms,
            ));
        }

        return $aggregate;
    }

    /**
     * Bootstrap event stream from existing projection (cut-over / legacy rows).
     *
     * @param  array<string, mixed>  $state
     */
    public static function initializeFromReadModel(string $word, array $state): self
    {
        $normalized = Str::lower(trim($word));
        $aggregate = new self($normalized);

        $aggregate->recordThat(DictionaryEntryCreated::make(
            $normalized,
            $state['phonetic'] ?? null,
            $state['audio_url'] ?? null,
            $state['source'] ?? 'user_save',
            (int) ($state['save_count'] ?? 0),
        ));

        $aggregate->recordThat(DictionaryContentReplaced::make(
            $normalized,
            $state['phonetic'] ?? null,
            $state['audio_url'] ?? null,
            $state['source'] ?? 'user_save',
            (bool) ($state['is_curated'] ?? false),
            self::normalizeMeanings($state['meanings'] ?? []),
            self::stringList($state['synonyms'] ?? []),
            self::stringList($state['antonyms'] ?? []),
        ));

        return $aggregate;
    }

    public function recordSave(?array $payload): void
    {
        $this->assertNotDeleted();

        if ($this->isCurated) {
            $this->recordThat(DictionarySaveCounted::make($this->word, $this->saveCount + 1));

            return;
        }

        if ($payload === null) {
            $this->recordThat(DictionarySaveCounted::make($this->word, $this->saveCount + 1));

            return;
        }

        $mergedMeanings = $this->meanings;
        $existingDefs = array_map(
            fn ($m) => Str::lower(trim((string) ($m['definition'] ?? ''))),
            $mergedMeanings
        );

        foreach ($payload['meanings'] ?? [] as $meaning) {
            if (! is_array($meaning)) {
                continue;
            }
            $definition = trim((string) ($meaning['definition'] ?? ''));
            if ($definition === '' || in_array(Str::lower($definition), $existingDefs, true)) {
                continue;
            }
            $mergedMeanings[] = self::normalizeMeaning($meaning);
            $existingDefs[] = Str::lower($definition);
        }

        $phonetic = $this->phonetic ?: ($payload['phonetic'] ?? null);
        $audioUrl = $this->audioUrl ?: ($payload['audio_url'] ?? null);
        $synonyms = array_values(array_unique([...$this->synonyms, ...self::stringList($payload['synonyms'] ?? [])]));
        $antonyms = array_values(array_unique([...$this->antonyms, ...self::stringList($payload['antonyms'] ?? [])]));

        $this->recordThat(DictionaryContentMerged::make(
            $this->word,
            $phonetic,
            $audioUrl,
            $mergedMeanings,
            $synonyms,
            $antonyms,
            $this->saveCount + 1,
        ));
    }

    public function curate(array $data): void
    {
        $this->assertNotDeleted();

        $word = Str::lower(trim((string) ($data['word'] ?? $this->word)));

        $this->recordThat(DictionaryContentReplaced::make(
            $word,
            $data['phonetic'] ?? null,
            $data['audio_url'] ?? null,
            'admin',
            true,
            self::normalizeMeanings($data['meanings'] ?? []),
            self::stringList($data['synonyms'] ?? []),
            self::stringList($data['antonyms'] ?? []),
        ));
    }

    public function delete(): void
    {
        $this->assertNotDeleted();
        $this->recordThat(DictionaryEntryDeleted::make($this->word));
    }

    public function isCurated(): bool
    {
        return $this->isCurated;
    }

    public function isDeleted(): bool
    {
        return $this->deleted;
    }

    /** @return array<string, mixed> */
    public function toClientPayload(): array
    {
        $meanings = [];
        foreach ($this->meanings as $meaning) {
            $examples = self::stringList($meaning['examples'] ?? []);
            $meanings[] = [
                'part_of_speech' => $meaning['part_of_speech'] ?? null,
                'definition' => $meaning['definition'] ?? '',
                'example' => $examples[0] ?? null,
                'examples' => $examples,
                'synonyms' => self::stringList($meaning['synonyms'] ?? []),
                'antonyms' => self::stringList($meaning['antonyms'] ?? []),
            ];
        }

        return [
            'word' => $this->word,
            'phonetic' => $this->phonetic,
            'audio_url' => $this->audioUrl,
            'meanings' => $meanings,
            'synonyms' => $this->synonyms,
            'antonyms' => $this->antonyms,
            'source' => 'flc',
            'curated' => $this->isCurated,
        ];
    }

    protected function applyDictionaryEntryCreated(DictionaryEntryCreated $event): void
    {
        $p = $event->payload;
        $this->word = $p['word'];
        $this->phonetic = $p['phonetic'] ?? null;
        $this->audioUrl = $p['audio_url'] ?? null;
        $this->source = $p['source'] ?? 'user_save';
        $this->saveCount = (int) ($p['save_count'] ?? 1);
        $this->isCurated = false;
        $this->deleted = false;
    }

    protected function applyDictionarySaveCounted(DictionarySaveCounted $event): void
    {
        $this->saveCount = (int) $event->payload['save_count'];
    }

    protected function applyDictionaryContentReplaced(DictionaryContentReplaced $event): void
    {
        $p = $event->payload;
        $this->word = $p['word'];
        $this->phonetic = $p['phonetic'] ?? null;
        $this->audioUrl = $p['audio_url'] ?? null;
        $this->source = $p['source'] ?? $this->source;
        $this->isCurated = (bool) ($p['is_curated'] ?? false);
        $this->meanings = self::normalizeMeanings($p['meanings'] ?? []);
        $this->synonyms = self::stringList($p['synonyms'] ?? []);
        $this->antonyms = self::stringList($p['antonyms'] ?? []);
    }

    protected function applyDictionaryContentMerged(DictionaryContentMerged $event): void
    {
        $p = $event->payload;
        $this->phonetic = $p['phonetic'] ?? null;
        $this->audioUrl = $p['audio_url'] ?? null;
        $this->meanings = self::normalizeMeanings($p['meanings'] ?? []);
        $this->synonyms = self::stringList($p['synonyms'] ?? []);
        $this->antonyms = self::stringList($p['antonyms'] ?? []);
        $this->saveCount = (int) $event->payload['save_count'];
    }

    protected function applyDictionaryEntryDeleted(DictionaryEntryDeleted $event): void
    {
        $this->deleted = true;
    }

    private function assertNotDeleted(): void
    {
        if ($this->deleted) {
            throw new DomainException('Dictionary entry is deleted.');
        }
    }

    /**
     * @param  list<mixed>  $meanings
     * @return list<array<string, mixed>>
     */
    private static function normalizeMeanings(array $meanings): array
    {
        $out = [];
        foreach ($meanings as $meaning) {
            if (! is_array($meaning)) {
                continue;
            }
            $normalized = self::normalizeMeaning($meaning);
            if ($normalized['definition'] === '') {
                continue;
            }
            $out[] = $normalized;
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $meaning
     * @return array<string, mixed>
     */
    private static function normalizeMeaning(array $meaning): array
    {
        $examples = self::stringList($meaning['examples'] ?? []);
        if ($examples === [] && ! empty($meaning['example']) && is_string($meaning['example'])) {
            $examples = [$meaning['example']];
        }

        return [
            'part_of_speech' => $meaning['part_of_speech'] ?? null,
            'definition' => trim((string) ($meaning['definition'] ?? '')),
            'examples' => $examples,
            'synonyms' => self::stringList($meaning['synonyms'] ?? []),
            'antonyms' => self::stringList($meaning['antonyms'] ?? []),
        ];
    }

    /**
     * @param  mixed  $values
     * @return list<string>
     */
    private static function stringList(mixed $values): array
    {
        if (! is_array($values)) {
            return [];
        }

        $out = [];
        foreach ($values as $value) {
            if (! is_string($value)) {
                continue;
            }
            $term = trim($value);
            if ($term !== '') {
                $out[$term] = $term;
            }
        }

        return array_values($out);
    }
}
