<?php

namespace Flc\WordChat\Application\Handler;

use Flc\Shared\Application\Command;
use Flc\Shared\Application\CommandHandler;
use Flc\WordChat\Application\Command\CompleteWordChatRun;
use Flc\WordChat\Application\LearningInsightRepository;
use Flc\WordChat\Application\WordChatInsightExtractor;
use Flc\WordChat\Application\WordChatMessageRepository;
use Flc\WordChat\Application\WordChatRunRepository;
use Flc\WordChat\Domain\LearningInsight;
use Flc\WordChat\Domain\WordChatMessage;
use Flc\WordChat\Domain\WordChatRun;
use Throwable;

final class CompleteWordChatRunHandler implements CommandHandler
{
    public function __construct(
        private readonly WordChatMessageRepository $messages,
        private readonly WordChatRunRepository $runs,
        private readonly WordChatInsightExtractor $insightExtractor,
        private readonly LearningInsightRepository $insights,
    ) {}

    public function handle(Command $command): mixed
    {
        assert($command instanceof CompleteWordChatRun);

        $existing = $this->runs->findByCursorRunForUser($command->userId, $command->cursorRunId);
        if ($existing !== null && $existing->status === 'finished' && $existing->assistantContent !== null) {
            return $this->existingAssistantPayload($command->userId, $existing);
        }

        $run = $existing ?? $this->runs->findByCursorRunForUser($command->userId, $command->cursorRunId);
        $userQuestion = '';
        if ($run !== null) {
            $userMessage = $this->messages->findById($command->userId, $run->userMessageId);
            $userQuestion = $userMessage?->content ?? '';
        }

        $extracted = $this->insightExtractor->extract(
            userId: $command->userId,
            userQuestion: $userQuestion,
            assistantReply: trim($command->assistantContent),
            sourceMessageId: null,
        );

        $content = $extracted['content'];
        if ($content === '') {
            $this->runs->markError($command->userId, $command->cursorRunId);

            return null;
        }

        $assistant = $this->messages->save(new WordChatMessage(
            id: null,
            userId: $command->userId,
            role: 'assistant',
            content: $content,
            cursorRunId: $command->cursorRunId,
        ));

        $this->runs->complete(
            userId: $command->userId,
            cursorRunId: $command->cursorRunId,
            assistantContent: $content,
            assistantMessageId: (int) $assistant->id,
        );

        $savedInsights = $this->persistInsights(
            userId: $command->userId,
            assistantMessageId: (int) $assistant->id,
            insights: $extracted['insights'],
        );

        $payload = $assistant->toApiArray();
        $payload['insights'] = array_map(
            fn ($insight) => $insight->toApiArray(),
            $savedInsights,
        );

        return $payload;
    }

    /**
     * @param  list<LearningInsight>  $insights
     * @return list<LearningInsight>
     */
    private function persistInsights(int $userId, int $assistantMessageId, array $insights): array
    {
        if ($insights === []) {
            return [];
        }

        try {
            $savedInsights = [];
            foreach ($insights as $insight) {
                $savedInsights[] = $this->insights->save(new LearningInsight(
                    id: null,
                    userId: $insight->userId,
                    vocabularyId: $insight->vocabularyId,
                    word: $insight->word,
                    insightType: $insight->insightType,
                    question: $insight->question,
                    content: $insight->content,
                    sourceMessageId: $assistantMessageId,
                    metadata: $insight->metadata,
                    quizEligible: $insight->quizEligible,
                    timesUsedInQuiz: $insight->timesUsedInQuiz,
                ));
            }

            $this->messages->updateMetadata($userId, $assistantMessageId, [
                'insight_ids' => array_map(fn ($item) => $item->id, $savedInsights),
            ]);

            return $savedInsights;
        } catch (Throwable $exception) {
            report($exception);

            return [];
        }
    }

    /** @return array<string, mixed>|null */
    private function existingAssistantPayload(int $userId, WordChatRun $run): ?array
    {
        if ($run->assistantMessageId === null) {
            return null;
        }

        $assistant = $this->messages->findById($userId, $run->assistantMessageId);
        if ($assistant === null) {
            return null;
        }

        $payload = $assistant->toApiArray();
        $insightIds = is_array($assistant->metadata['insight_ids'] ?? null)
            ? $assistant->metadata['insight_ids']
            : [];

        $payload['insights'] = [];
        foreach ($insightIds as $insightId) {
            try {
                $insight = $this->insights->findForUser($userId, (int) $insightId);
                if ($insight !== null) {
                    $payload['insights'][] = $insight->toApiArray();
                }
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        return $payload;
    }
}
