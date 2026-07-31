<?php

namespace Tests\Unit;

use Flc\Puzzle\Application\Handler\GetNextWordlePuzzleHandler;
use Flc\Puzzle\Application\Query\GetNextWordlePuzzle;
use Flc\Shared\Application\Clock;
use Flc\Vocabulary\Application\Repository\UserVocabularyRepository;
use Flc\Vocabulary\Domain\UserVocabulary;
use PHPUnit\Framework\TestCase;

class GetNextWordlePuzzleHandlerTest extends TestCase
{
    public function test_is_eligible_word_requires_exactly_five_letters(): void
    {
        $this->assertTrue(GetNextWordlePuzzleHandler::isEligibleWord('happy'));
        $this->assertTrue(GetNextWordlePuzzleHandler::isEligibleWord(' STARE '));
        $this->assertFalse(GetNextWordlePuzzleHandler::isEligibleWord('cat'));
        $this->assertFalse(GetNextWordlePuzzleHandler::isEligibleWord('planet'));
        $this->assertFalse(GetNextWordlePuzzleHandler::isEligibleWord('ice-cream'));
        $this->assertFalse(GetNextWordlePuzzleHandler::isEligibleWord('happé'));
    }

    public function test_excludes_seen_vocabulary_until_pool_exhausted(): void
    {
        $words = [
            $this->vocab(1, 'apple'),
            $this->vocab(2, 'bread'),
            $this->vocab(3, 'chair'),
            $this->vocab(4, 'tool'), // not eligible
            $this->vocab(5, 'dance'),
        ];

        $handler = new GetNextWordlePuzzleHandler(
            $this->repository($words),
            $this->clock('2026-07-31 12:00:00'),
        );

        $first = $handler->handle(new GetNextWordlePuzzle(1, []));
        $this->assertNotNull($first);
        $this->assertSame(4, $first['eligible_count']);
        $this->assertContains($first['vocabulary_id'], [1, 2, 3, 5]);

        $seen = [(int) $first['vocabulary_id']];
        $ids = [$seen[0]];

        for ($i = 0; $i < 3; $i++) {
            $next = $handler->handle(new GetNextWordlePuzzle(1, $seen));
            $this->assertNotNull($next);
            $this->assertNotContains($next['vocabulary_id'], $seen);
            $seen[] = (int) $next['vocabulary_id'];
            $ids[] = (int) $next['vocabulary_id'];
        }

        sort($ids);
        $this->assertSame([1, 2, 3, 5], $ids);

        // Full cycle used — fallback allows a previously seen word again.
        $again = $handler->handle(new GetNextWordlePuzzle(1, $seen));
        $this->assertNotNull($again);
        $this->assertContains($again['vocabulary_id'], [1, 2, 3, 5]);
    }

    /** @param  list<UserVocabulary>  $words */
    private function repository(array $words): UserVocabularyRepository
    {
        return new class($words) implements UserVocabularyRepository
        {
            /** @param  list<UserVocabulary>  $words */
            public function __construct(private array $words) {}

            public function listForUser(int $userId): array
            {
                return $this->words;
            }

            public function findForUser(int $userId, int $vocabularyId): ?UserVocabulary
            {
                return null;
            }

            public function findByUserAndWord(int $userId, string $word): ?UserVocabulary
            {
                return null;
            }

            public function save(UserVocabulary $vocabulary): UserVocabulary
            {
                return $vocabulary;
            }

            public function deleteForUser(int $userId, int $vocabularyId): bool
            {
                return false;
            }
        };
    }

    private function clock(string $now): Clock
    {
        return new class($now) implements Clock
        {
            public function __construct(private string $now) {}

            public function now(): \DateTimeImmutable
            {
                return new \DateTimeImmutable($this->now);
            }
        };
    }

    private function vocab(int $id, string $word): UserVocabulary
    {
        return new UserVocabulary(
            id: $id,
            userId: 1,
            dictionaryEntryId: $id,
            word: $word,
            phonetic: null,
            meanings: [['definition' => 'x']],
            timesQuizzed: 0,
            lastQuizzedAt: null,
        );
    }
}
