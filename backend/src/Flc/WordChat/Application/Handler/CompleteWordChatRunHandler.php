<?php

namespace Flc\WordChat\Application\Handler;

use Flc\Shared\Application\Command;
use Flc\Shared\Application\CommandHandler;
use Flc\WordChat\Application\Command\CompleteWordChatRun;
use Flc\WordChat\Application\WordChatMessageRepository;
use Flc\WordChat\Application\WordChatRunRepository;
use Flc\WordChat\Domain\WordChatMessage;

final class CompleteWordChatRunHandler implements CommandHandler
{
    public function __construct(
        private readonly WordChatMessageRepository $messages,
        private readonly WordChatRunRepository $runs,
    ) {}

    public function handle(Command $command): mixed
    {
        assert($command instanceof CompleteWordChatRun);

        $existing = $this->runs->findByCursorRunForUser($command->userId, $command->cursorRunId);
        if ($existing !== null && $existing->status === 'finished' && $existing->assistantContent !== null) {
            return [
                'id' => $existing->assistantMessageId,
                'role' => 'assistant',
                'content' => $existing->assistantContent,
                'cursor_run_id' => $command->cursorRunId,
            ];
        }

        $content = trim($command->assistantContent);
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

        return $assistant->toApiArray();
    }
}
