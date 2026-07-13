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

        $payload = $lookup ?? [
            'word' => $word,
            'phonetic' => $command->phonetic,
            'audio_url' => null,
            'meanings' => $rawMeanings,
            'synonyms' => [],
            'antonyms' => [],
            'source' => 'user_save',
        ];
        $this->commands->dispatch(new UpsertDictionaryOnSave($word, is_array($payload) ? $payload : null));

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
            ];
        }

        return $out;
    }
}
