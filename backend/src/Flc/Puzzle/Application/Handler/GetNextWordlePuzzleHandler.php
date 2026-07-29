<?php

namespace Flc\Puzzle\Application\Handler;

use Flc\Puzzle\Application\Query\GetNextWordlePuzzle;
use Flc\Puzzle\Domain\WordleGrader;
use Flc\Puzzle\Domain\WordleKeyboardBuilder;
use Flc\Shared\Application\Clock;
use Flc\Shared\Application\Query;
use Flc\Shared\Application\QueryHandler;
use Flc\Vocabulary\Application\Repository\UserVocabularyRepository;
use Flc\Vocabulary\Domain\UserVocabulary;

final class GetNextWordlePuzzleHandler implements QueryHandler
{
    public function __construct(
        private readonly UserVocabularyRepository $vocabularies,
        private readonly Clock $clock,
    ) {}

    public function handle(Query $query): mixed
    {
        assert($query instanceof GetNextWordlePuzzle);

        $eligible = array_values(array_filter(
            $this->vocabularies->listForUser($query->userId),
            fn (UserVocabulary $v) => self::isEligibleWord($v->word),
        ));

        if ($eligible === []) {
            return null;
        }

        $target = $this->pickWeighted($eligible);

        if ($target === null) {
            return null;
        }

        $word = strtolower(trim($target->word));

        return [
            'vocabulary_id' => $target->id,
            'mode' => 'wordle',
            'word_length' => WordleGrader::WORD_LENGTH,
            'max_guesses' => WordleGrader::MAX_GUESSES,
            'correct_word' => $word,
            'keyboard_letters' => WordleKeyboardBuilder::build($word, null, $target->id),
        ];
    }

    public static function isEligibleWord(string $word): bool
    {
        $normalized = strtolower(trim($word));

        return strlen($normalized) === WordleGrader::WORD_LENGTH
            && (bool) preg_match('/^[a-z]+$/', $normalized);
    }

    /**
     * @param  list<UserVocabulary>  $vocabularies
     */
    private function pickWeighted(array $vocabularies): ?UserVocabulary
    {
        $weights = [];
        $now = $this->clock->now();

        foreach ($vocabularies as $vocabulary) {
            $base = 1 / ($vocabulary->timesQuizzed + 1);
            $decay = 1.0;

            if ($vocabulary->lastQuizzedAt !== null) {
                $last = new \DateTimeImmutable($vocabulary->lastQuizzedAt);
                $hours = max(0, ($now->getTimestamp() - $last->getTimestamp()) / 3600);
                $decay = min(1.0, max(0.15, $hours / 24));
            }

            $weights[] = max(0.01, $base * $decay);
        }

        $total = array_sum($weights);
        $roll = mt_rand() / mt_getrandmax() * $total;
        $cumulative = 0.0;

        foreach ($vocabularies as $index => $vocabulary) {
            $cumulative += $weights[$index];
            if ($roll <= $cumulative) {
                return $vocabulary;
            }
        }

        return $vocabularies[0] ?? null;
    }
}
