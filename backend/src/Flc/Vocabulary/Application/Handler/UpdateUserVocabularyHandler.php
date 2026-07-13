<?php

namespace Flc\Vocabulary\Application\Handler;

use Flc\Shared\Application\Command;
use Flc\Shared\Application\CommandHandler;
use Flc\Vocabulary\Application\Command\UpdateUserVocabulary;
use Flc\Vocabulary\Application\Repository\UserVocabularyRepository;
use RuntimeException;

final class UpdateUserVocabularyHandler implements CommandHandler
{
    public function __construct(
        private readonly UserVocabularyRepository $vocabularies,
    ) {}

    public function handle(Command $command): mixed
    {
        assert($command instanceof UpdateUserVocabulary);

        $vocabulary = $this->vocabularies->findForUser($command->userId, $command->vocabularyId);
        if ($vocabulary === null) {
            throw new RuntimeException('Vocabulary not found.');
        }

        if (array_key_exists('phonetic', $command->data)) {
            $vocabulary->phonetic = $command->data['phonetic'];
        }
        if (array_key_exists('meanings', $command->data) && is_array($command->data['meanings'])) {
            $vocabulary->meanings = $command->data['meanings'];
            $examples = [];
            foreach (array_slice($vocabulary->meanings, 0, 5) as $meaning) {
                if (! empty($meaning['example'])) {
                    $examples[] = [
                        'example' => $meaning['example'],
                        'definition_ref' => $meaning['definition'] ?? null,
                    ];
                }
            }
            $vocabulary->examples = $examples;
        }

        return $this->vocabularies->save($vocabulary);
    }
}
