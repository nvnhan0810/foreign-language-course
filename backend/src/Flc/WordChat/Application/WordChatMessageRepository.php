<?php

namespace Flc\WordChat\Application;

use Flc\WordChat\Domain\WordChatMessage;

interface WordChatMessageRepository
{
    public function save(WordChatMessage $message): WordChatMessage;

    /**
     * @return list<WordChatMessage>
     */
    public function listForUser(int $userId, ?int $beforeId = null, int $limit = 50): array;
}
