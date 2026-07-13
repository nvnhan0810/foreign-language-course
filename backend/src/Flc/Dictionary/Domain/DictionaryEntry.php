<?php

namespace Flc\Dictionary\Domain;

use Flc\Shared\Support\Text;

final class DictionaryEntry
{
    /**
     * @param  list<array<string, mixed>>  $meanings
     * @param  list<string>  $synonyms
     * @param  list<string>  $antonyms
     */
    public function __construct(
        public string $word,
        public ?string $phonetic = null,
        public ?string $audioUrl = null,
        public string $source = 'user_save',
        public bool $isCurated = false,
        public int $saveCount = 0,
        public array $meanings = [],
        public array $synonyms = [],
        public array $antonyms = [],
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function createFromPayload(string $word, array $payload): self
    {
        $normalized = Text::lower(trim($word));

        return new self(
            word: $normalized,
            phonetic: $payload['phonetic'] ?? null,
            audioUrl: $payload['audio_url'] ?? null,
            source: $payload['source'] ?? 'user_save',
            isCurated: false,
            saveCount: 1,
            meanings: self::normalizeMeanings($payload['meanings'] ?? []),
            synonyms: self::stringList($payload['synonyms'] ?? []),
            antonyms: self::stringList($payload['antonyms'] ?? []),
        );
    }

    public function recordSave(?array $payload): void
    {
        if ($this->isCurated || $payload === null) {
            $this->saveCount++;

            return;
        }

        $mergedMeanings = $this->meanings;
        $existingDefs = array_map(
            fn ($m) => Text::lower(trim((string) ($m['definition'] ?? ''))),
            $mergedMeanings
        );

        foreach ($payload['meanings'] ?? [] as $meaning) {
            if (! is_array($meaning)) {
                continue;
            }
            $definition = trim((string) ($meaning['definition'] ?? ''));
            if ($definition === '' || in_array(Text::lower($definition), $existingDefs, true)) {
                continue;
            }
            $mergedMeanings[] = self::normalizeMeaning($meaning);
            $existingDefs[] = Text::lower($definition);
        }

        $this->phonetic = $this->phonetic ?: ($payload['phonetic'] ?? null);
        $this->audioUrl = $this->audioUrl ?: ($payload['audio_url'] ?? null);
        $this->synonyms = array_values(array_unique([...$this->synonyms, ...self::stringList($payload['synonyms'] ?? [])]));
        $this->antonyms = array_values(array_unique([...$this->antonyms, ...self::stringList($payload['antonyms'] ?? [])]));
        $this->meanings = $mergedMeanings;
        $this->saveCount++;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function curate(array $data): void
    {
        $this->word = Text::lower(trim((string) ($data['word'] ?? $this->word)));
        $this->phonetic = $data['phonetic'] ?? null;
        $this->audioUrl = $data['audio_url'] ?? null;
        $this->source = 'admin';
        $this->isCurated = true;
        $this->meanings = self::normalizeMeanings($data['meanings'] ?? []);
        $this->synonyms = self::stringList($data['synonyms'] ?? []);
        $this->antonyms = self::stringList($data['antonyms'] ?? []);
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

    /**
     * @param  list<mixed>  $meanings
     * @return list<array<string, mixed>>
     */
    public static function normalizeMeanings(array $meanings): array
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
    public static function normalizeMeaning(array $meaning): array
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
     * @return list<string>
     */
    public static function stringList(mixed $values): array
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
