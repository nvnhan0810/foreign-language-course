<?php

namespace Flc\Puzzle\Application\Handler;

use Flc\Puzzle\Application\Query\GetScrambleHint;
use Flc\Shared\Application\Query;
use Flc\Shared\Application\QueryHandler;
use Flc\Vocabulary\Application\Repository\UserVocabularyRepository;
use Flc\WordChat\Application\LearningInsightRepository;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final class GetScrambleHintHandler implements QueryHandler
{
    public function __construct(
        private readonly UserVocabularyRepository $vocabularies,
        private readonly LearningInsightRepository $insights,
    ) {}

    public function handle(Query $query): mixed
    {
        assert($query instanceof GetScrambleHint);

        $vocabulary = $this->vocabularies->findForUser($query->userId, $query->vocabularyId);

        if ($vocabulary === null) {
            throw new AccessDeniedHttpException();
        }

        $insight = $this->insights->findEligibleForVocabulary($query->userId, $vocabulary->id);
        if ($insight !== null && trim($insight->content) !== '') {
            return [
                'vocabulary_id' => $vocabulary->id,
                'definition' => $insight->content,
                'part_of_speech' => null,
                'source' => 'word_chat_insight',
            ];
        }

        $meaning = $vocabulary->meanings[0] ?? null;
        $definition = is_array($meaning)
            ? trim((string) ($meaning['definition'] ?? ''))
            : '';

        if ($definition === '') {
            $definition = $vocabulary->word;
        }

        $partOfSpeech = is_array($meaning) && isset($meaning['part_of_speech'])
            ? (string) $meaning['part_of_speech']
            : null;

        return [
            'vocabulary_id' => $vocabulary->id,
            'definition' => $definition,
            'part_of_speech' => $partOfSpeech !== '' ? $partOfSpeech : null,
        ];
    }
}
