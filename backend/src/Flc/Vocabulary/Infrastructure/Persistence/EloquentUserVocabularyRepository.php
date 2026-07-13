<?php

namespace Flc\Vocabulary\Infrastructure\Persistence;

use App\Models\Vocabulary;
use App\Models\VocabularyExample;
use Flc\Vocabulary\Application\Repository\UserVocabularyRepository;
use Flc\Vocabulary\Domain\UserVocabulary;
use Illuminate\Support\Facades\DB;

final class EloquentUserVocabularyRepository implements UserVocabularyRepository
{
    public function listForUser(int $userId): array
    {
        return Vocabulary::query()
            ->where('user_id', $userId)
            ->with('examples')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Vocabulary $model) => $this->toDomain($model))
            ->all();
    }

    public function findForUser(int $userId, int $vocabularyId): ?UserVocabulary
    {
        $model = Vocabulary::query()
            ->where('user_id', $userId)
            ->where('id', $vocabularyId)
            ->with('examples')
            ->first();

        return $model ? $this->toDomain($model) : null;
    }

    public function findByUserAndWord(int $userId, string $word): ?UserVocabulary
    {
        $model = Vocabulary::query()
            ->where('user_id', $userId)
            ->where('word', $word)
            ->with('examples')
            ->first();

        return $model ? $this->toDomain($model) : null;
    }

    public function save(UserVocabulary $vocabulary): UserVocabulary
    {
        return DB::transaction(function () use ($vocabulary) {
            if ($vocabulary->id === null) {
                $model = Vocabulary::query()->create([
                    'user_id' => $vocabulary->userId,
                    'word' => $vocabulary->word,
                    'phonetic' => $vocabulary->phonetic,
                    'meanings' => $vocabulary->meanings,
                ]);
            } else {
                $model = Vocabulary::query()
                    ->where('user_id', $vocabulary->userId)
                    ->where('id', $vocabulary->id)
                    ->firstOrFail();
                $model->update([
                    'phonetic' => $vocabulary->phonetic,
                    'meanings' => $vocabulary->meanings,
                ]);
                $model->examples()->delete();
            }

            foreach ($vocabulary->examples as $example) {
                VocabularyExample::query()->create([
                    'vocabulary_id' => $model->id,
                    'example' => $example['example'],
                    'definition_ref' => $example['definition_ref'] ?? null,
                ]);
            }

            return $this->toDomain($model->fresh('examples'));
        });
    }

    public function deleteForUser(int $userId, int $vocabularyId): bool
    {
        $deleted = Vocabulary::query()
            ->where('user_id', $userId)
            ->where('id', $vocabularyId)
            ->delete();

        return $deleted > 0;
    }

    private function toDomain(Vocabulary $model): UserVocabulary
    {
        return new UserVocabulary(
            id: $model->id,
            userId: $model->user_id,
            word: $model->word,
            phonetic: $model->phonetic,
            meanings: is_array($model->meanings) ? $model->meanings : [],
            examples: $model->examples->map(fn (VocabularyExample $ex) => [
                'id' => $ex->id,
                'vocabulary_id' => $ex->vocabulary_id,
                'example' => $ex->example,
                'definition_ref' => $ex->definition_ref,
                'created_at' => optional($ex->created_at)?->toISOString(),
                'updated_at' => optional($ex->updated_at)?->toISOString(),
            ])->all(),
            timesQuizzed: (int) $model->times_quizzed,
            lastQuizzedAt: optional($model->last_quizzed_at)?->toISOString(),
            lastCorrectAt: optional($model->last_correct_at)?->toISOString(),
            createdAt: optional($model->created_at)?->toISOString(),
            updatedAt: optional($model->updated_at)?->toISOString(),
        );
    }
}
