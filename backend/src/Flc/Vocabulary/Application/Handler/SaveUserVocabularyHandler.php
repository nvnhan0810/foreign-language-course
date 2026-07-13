<?php

namespace Flc\Vocabulary\Application\Handler;

use Flc\Dictionary\Application\Command\UpsertDictionaryOnSave;
use Flc\Dictionary\Application\Query\LookupWord;
use Flc\Shared\Application\Command;
use Flc\Shared\Application\CommandBus;
use Flc\Shared\Application\CommandHandler;
use Flc\Shared\Application\QueryBus;
use Flc\Vocabulary\Application\Command\SaveUserVocabulary;
use Flc\Vocabulary\Application\Repository\UserVocabularyRepository;
use Flc\Vocabulary\Domain\UserVocabulary;
use Flc\Shared\Support\Text;

final class SaveUserVocabularyHandler implements CommandHandler
{
    public function __construct(
        private readonly UserVocabularyRepository $vocabularies,
        private readonly QueryBus $queries,
        private readonly CommandBus $commands,
    ) {}

    public function handle(Command $command): mixed
    {
        assert($command instanceof SaveUserVocabulary);

        $word = Text::lower(trim($command->word));
        if ($word === '') {
            return null;
        }

        $existing = $this->vocabularies->findByUserAndWord($command->userId, $word);
        if ($existing !== null) {
            return ['vocabulary' => $existing, 'created' => false];
        }

        /** @var array<string, mixed>|null $lookup */
        $lookup = $this->queries->ask(new LookupWord($word));
        $rawMeanings = $command->meanings ?? $lookup['meanings'] ?? [];
        $meanings = $this->meaningsForVocabulary(is_array($rawMeanings) ? $rawMeanings : []);
        $meanings = $this->ensureRelatedWords($meanings, is_array($lookup) ? $lookup : null);

        $examples = [];
        foreach (array_slice($meanings, 0, 5) as $meaning) {
            if (! empty($meaning['example'])) {
                $examples[] = [
                    'example' => $meaning['example'],
                    'definition_ref' => $meaning['definition'] ?? null,
                ];
            }
        }

        $vocabulary = $this->vocabularies->save(new UserVocabulary(
            id: null,
            userId: $command->userId,
            word: $word,
            phonetic: $command->phonetic ?? $lookup['phonetic'] ?? null,
            meanings: $meanings,
            examples: $examples,
        ));

        $payload = is_array($lookup) ? $lookup : [
            'word' => $word,
            'phonetic' => $command->phonetic,
            'audio_url' => null,
            'meanings' => $meanings,
            'synonyms' => $this->collectRelated($meanings, 'synonyms'),
            'antonyms' => $this->collectRelated($meanings, 'antonyms'),
            'source' => 'user_save',
        ];
        $payload['meanings'] = $meanings;
        $payload['synonyms'] = $this->stringList($payload['synonyms'] ?? []) !== []
            ? $this->stringList($payload['synonyms'] ?? [])
            : $this->collectRelated($meanings, 'synonyms');
        $payload['antonyms'] = $this->stringList($payload['antonyms'] ?? []) !== []
            ? $this->stringList($payload['antonyms'] ?? [])
            : $this->collectRelated($meanings, 'antonyms');

        $this->commands->dispatch(new UpsertDictionaryOnSave($word, $payload));

        return ['vocabulary' => $vocabulary, 'created' => true];
    }

    /**
     * @param  list<array<string, mixed>>  $meanings
     * @return list<array<string, mixed>>
     */
    private function meaningsForVocabulary(array $meanings): array
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
                'examples' => $examples,
                'synonyms' => $this->stringList($meaning['synonyms'] ?? null),
                'antonyms' => $this->stringList($meaning['antonyms'] ?? null),
            ];
        }

        return $out;
    }

    /**
     * Merge synonyms/antonyms from the latest lookup so saved vocab always keeps related words.
     *
     * @param  list<array<string, mixed>>  $meanings
     * @param  array<string, mixed>|null  $lookup
     * @return list<array<string, mixed>>
     */
    private function ensureRelatedWords(array $meanings, ?array $lookup): array
    {
        if ($meanings === [] || $lookup === null) {
            return $meanings;
        }

        $entrySynonyms = $this->collectRelatedFromPayload($lookup, 'synonyms');
        $entryAntonyms = $this->collectRelatedFromPayload($lookup, 'antonyms');
        $lookupMeanings = is_array($lookup['meanings'] ?? null) ? $lookup['meanings'] : [];

        foreach ($meanings as $index => $meaning) {
            $lookupMeaning = is_array($lookupMeanings[$index] ?? null) ? $lookupMeanings[$index] : null;

            if ($this->stringList($meaning['synonyms'] ?? null) === []) {
                $fromLookupMeaning = $lookupMeaning !== null
                    ? $this->stringList($lookupMeaning['synonyms'] ?? null)
                    : [];
                $meanings[$index]['synonyms'] = $fromLookupMeaning !== []
                    ? $fromLookupMeaning
                    : ($index === 0 ? $entrySynonyms : []);
            }

            if ($this->stringList($meaning['antonyms'] ?? null) === []) {
                $fromLookupMeaning = $lookupMeaning !== null
                    ? $this->stringList($lookupMeaning['antonyms'] ?? null)
                    : [];
                $meanings[$index]['antonyms'] = $fromLookupMeaning !== []
                    ? $fromLookupMeaning
                    : ($index === 0 ? $entryAntonyms : []);
            }
        }

        // If still empty on first meaning, force entry-level lists there.
        if ($this->stringList($meanings[0]['synonyms'] ?? null) === [] && $entrySynonyms !== []) {
            $meanings[0]['synonyms'] = $entrySynonyms;
        }
        if ($this->stringList($meanings[0]['antonyms'] ?? null) === [] && $entryAntonyms !== []) {
            $meanings[0]['antonyms'] = $entryAntonyms;
        }

        return $meanings;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<string>
     */
    private function collectRelatedFromPayload(array $payload, string $key): array
    {
        $terms = $this->stringList($payload[$key] ?? null);
        foreach ($payload['meanings'] ?? [] as $meaning) {
            if (! is_array($meaning)) {
                continue;
            }
            $terms = [...$terms, ...$this->stringList($meaning[$key] ?? null)];
        }

        return array_values(array_unique($terms));
    }

    /**
     * @param  list<array<string, mixed>>  $meanings
     * @return list<string>
     */
    private function collectRelated(array $meanings, string $key): array
    {
        $terms = [];
        foreach ($meanings as $meaning) {
            $terms = [...$terms, ...$this->stringList($meaning[$key] ?? null)];
        }

        return array_values(array_unique($terms));
    }

    /**
     * @return list<string>
     */
    private function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $out = [];
        foreach ($value as $item) {
            if (is_string($item) && trim($item) !== '') {
                $out[] = trim($item);
            }
        }

        return array_values(array_unique($out));
    }
}
