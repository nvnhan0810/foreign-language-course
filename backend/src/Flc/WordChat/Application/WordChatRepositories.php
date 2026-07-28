<?php

namespace Flc\WordChat\Application;

interface WordChatAgentRepository
{
    public function findForUser(int $userId): ?array;

    public function saveAgent(int $userId, string $cursorAgentId, string $status = 'active'): array;

    public function markLastRun(int $userId): void;

    public function archiveForUser(int $userId): void;
}

interface WordChatMessageRepository
{
    public function save(\Flc\WordChat\Domain\WordChatMessage $message): \Flc\WordChat\Domain\WordChatMessage;

    /**
     * @return list<\Flc\WordChat\Domain\WordChatMessage>
     */
    public function listForUser(int $userId, ?int $beforeId = null, int $limit = 50): array;
}

interface WordChatRunRepository
{
    public function save(\Flc\WordChat\Domain\WordChatRun $run): \Flc\WordChat\Domain\WordChatRun;

    public function findByCursorRunForUser(int $userId, string $cursorRunId): ?\Flc\WordChat\Domain\WordChatRun;

    public function complete(
        int $userId,
        string $cursorRunId,
        string $assistantContent,
        int $assistantMessageId,
    ): void;

    public function markError(int $userId, string $cursorRunId): void;
}
