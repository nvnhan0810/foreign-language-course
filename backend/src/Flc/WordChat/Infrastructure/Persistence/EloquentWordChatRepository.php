<?php

namespace Flc\WordChat\Infrastructure\Persistence;

use App\Models\WordChatAgent as WordChatAgentModel;
use App\Models\WordChatMessage as WordChatMessageModel;
use App\Models\WordChatRun as WordChatRunModel;
use Flc\WordChat\Application\WordChatAgentRepository;
use Flc\WordChat\Application\WordChatMessageRepository;
use Flc\WordChat\Application\WordChatRunRepository;
use Flc\WordChat\Domain\WordChatMessage;
use Flc\WordChat\Domain\WordChatRun;
use Illuminate\Support\Facades\DB;

final class EloquentWordChatAgentRepository implements WordChatAgentRepository
{
    public function findForUser(int $userId): ?array
    {
        $model = WordChatAgentModel::query()
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->first();

        if ($model === null) {
            return null;
        }

        return [
            'id' => (int) $model->id,
            'cursor_agent_id' => (string) $model->cursor_agent_id,
            'status' => (string) $model->status,
        ];
    }

    public function saveAgent(int $userId, string $cursorAgentId, string $status = 'active'): array
    {
        $model = WordChatAgentModel::query()->updateOrCreate(
            ['user_id' => $userId],
            [
                'cursor_agent_id' => $cursorAgentId,
                'status' => $status,
            ],
        );

        return [
            'id' => (int) $model->id,
            'cursor_agent_id' => (string) $model->cursor_agent_id,
            'status' => (string) $model->status,
        ];
    }

    public function markLastRun(int $userId): void
    {
        WordChatAgentModel::query()
            ->where('user_id', $userId)
            ->update(['last_run_at' => now()]);
    }

    public function archiveForUser(int $userId): void
    {
        WordChatAgentModel::query()
            ->where('user_id', $userId)
            ->update(['status' => 'archived']);
    }
}

final class EloquentWordChatMessageRepository implements WordChatMessageRepository
{
    public function save(WordChatMessage $message): WordChatMessage
    {
        $model = WordChatMessageModel::query()->create([
            'user_id' => $message->userId,
            'role' => $message->role,
            'content' => $message->content,
            'cursor_run_id' => $message->cursorRunId,
            'metadata' => $message->metadata,
        ]);

        return $this->toDomain($model);
    }

    public function listForUser(int $userId, ?int $beforeId = null, int $limit = 50): array
    {
        $query = WordChatMessageModel::query()
            ->where('user_id', $userId)
            ->orderByDesc('id')
            ->limit(max(1, min(100, $limit)));

        if ($beforeId !== null) {
            $query->where('id', '<', $beforeId);
        }

        $items = $query->get()->reverse()->values();

        return $items->map(fn (WordChatMessageModel $model) => $this->toDomain($model))->all();
    }

    private function toDomain(WordChatMessageModel $model): WordChatMessage
    {
        return new WordChatMessage(
            id: (int) $model->id,
            userId: (int) $model->user_id,
            role: (string) $model->role,
            content: (string) $model->content,
            cursorRunId: $model->cursor_run_id,
            metadata: is_array($model->metadata) ? $model->metadata : null,
            createdAt: $model->created_at?->toIso8601String(),
        );
    }
}

final class EloquentWordChatRunRepository implements WordChatRunRepository
{
    public function save(WordChatRun $run): WordChatRun
    {
        $model = WordChatRunModel::query()->create([
            'user_id' => $run->userId,
            'word_chat_agent_id' => $run->wordChatAgentId,
            'cursor_agent_id' => $run->cursorAgentId,
            'cursor_run_id' => $run->cursorRunId,
            'user_message_id' => $run->userMessageId,
            'assistant_message_id' => $run->assistantMessageId,
            'status' => $run->status,
            'assistant_content' => $run->assistantContent,
        ]);

        return new WordChatRun(
            id: (int) $model->id,
            userId: (int) $model->user_id,
            wordChatAgentId: (int) $model->word_chat_agent_id,
            cursorAgentId: (string) $model->cursor_agent_id,
            cursorRunId: (string) $model->cursor_run_id,
            userMessageId: (int) $model->user_message_id,
            assistantMessageId: $model->assistant_message_id !== null ? (int) $model->assistant_message_id : null,
            status: (string) $model->status,
            assistantContent: $model->assistant_content,
        );
    }

    public function findByCursorRunForUser(int $userId, string $cursorRunId): ?WordChatRun
    {
        $model = WordChatRunModel::query()
            ->where('user_id', $userId)
            ->where('cursor_run_id', $cursorRunId)
            ->first();

        if ($model === null) {
            return null;
        }

        return new WordChatRun(
            id: (int) $model->id,
            userId: (int) $model->user_id,
            wordChatAgentId: (int) $model->word_chat_agent_id,
            cursorAgentId: (string) $model->cursor_agent_id,
            cursorRunId: (string) $model->cursor_run_id,
            userMessageId: (int) $model->user_message_id,
            assistantMessageId: $model->assistant_message_id !== null ? (int) $model->assistant_message_id : null,
            status: (string) $model->status,
            assistantContent: $model->assistant_content,
        );
    }

    public function complete(
        int $userId,
        string $cursorRunId,
        string $assistantContent,
        int $assistantMessageId,
    ): void {
        DB::transaction(function () use ($userId, $cursorRunId, $assistantContent, $assistantMessageId): void {
            $run = WordChatRunModel::query()
                ->where('user_id', $userId)
                ->where('cursor_run_id', $cursorRunId)
                ->lockForUpdate()
                ->first();

            if ($run === null || $run->status === 'finished') {
                return;
            }

            $run->update([
                'status' => 'finished',
                'assistant_content' => $assistantContent,
                'assistant_message_id' => $assistantMessageId,
            ]);
        });
    }

    public function markError(int $userId, string $cursorRunId): void
    {
        WordChatRunModel::query()
            ->where('user_id', $userId)
            ->where('cursor_run_id', $cursorRunId)
            ->update(['status' => 'error']);
    }
}
