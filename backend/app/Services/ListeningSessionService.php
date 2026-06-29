<?php

namespace App\Services;

use App\Models\ListeningAssessment;
use App\Models\ListeningQuestion;
use App\Models\MediaItem;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ListeningSessionService
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function sessionOptions(MediaItem $mediaItem): array
    {
        $bankCount = $mediaItem->listeningQuestions()->count();
        $bankReady = $mediaItem->isQuestionBankReady();

        $options = [];

        foreach ([ListeningAssessment::TYPE_QUIZ, ListeningAssessment::TYPE_TEST, ListeningAssessment::TYPE_EXAM] as $type) {
            $config = config("listening.assessments.{$type}");

            if (! is_array($config)) {
                continue;
            }

            $sessionSize = (int) $config['question_count'];

            $options[] = [
                'type' => $type,
                'title' => "{$mediaItem->title} — {$config['title_suffix']}",
                'question_count' => $sessionSize,
                'time_limit_minutes' => $config['time_limit_minutes'],
                'available' => $bankReady && $bankCount >= $sessionSize,
                'bank_count' => $bankCount,
                'bank_status' => $mediaItem->question_bank_status,
            ];
        }

        return $options;
    }

    /**
     * @return array<string, mixed>
     */
    public function resumeOrStartSession(MediaItem $mediaItem, User $user, string $type): array
    {
        $unfinished = $this->findUnfinishedAssessment($mediaItem, $user, $type);

        if ($unfinished !== null) {
            return $this->formatExistingSession($unfinished);
        }

        return $this->startSession($mediaItem, $user, $type);
    }

    /**
     * @return array<string, mixed>
     */
    public function startSession(MediaItem $mediaItem, User $user, string $type): array
    {
        $config = config("listening.assessments.{$type}");

        if (! is_array($config)) {
            throw new RuntimeException("Unknown assessment type: {$type}");
        }

        $sessionSize = (int) $config['question_count'];
        $bankCount = $mediaItem->listeningQuestions()->count();

        if (! $mediaItem->isQuestionBankReady()) {
            throw new RuntimeException('Question bank is not ready yet.');
        }

        if ($bankCount < $sessionSize) {
            throw new RuntimeException("Not enough questions in bank (need {$sessionSize}, have {$bankCount}).");
        }

        return DB::transaction(function () use ($mediaItem, $user, $type, $config, $sessionSize) {
            /** @var Collection<int, ListeningQuestion> $selected */
            $selected = $this->pickRandomQuestions($mediaItem, $sessionSize);

            $questionIds = $selected->pluck('id')->all();

            $assessment = ListeningAssessment::query()->create([
                'media_item_id' => $mediaItem->id,
                'user_id' => $user->id,
                'type' => $type,
                'title' => "{$mediaItem->title} — {$config['title_suffix']}",
                'description' => "Random {$type} session from question bank",
                'question_count' => count($questionIds),
                'question_ids' => $questionIds,
                'time_limit_minutes' => $config['time_limit_minutes'],
                'status' => ListeningAssessment::STATUS_READY,
                'generated_at' => now(),
            ]);

            return [
                'assessment_id' => $assessment->id,
                'type' => $type,
                'title' => $assessment->title,
                'time_limit_minutes' => $assessment->time_limit_minutes,
                'question_count' => count($questionIds),
                'questions' => $this->formatQuestionsForClient($selected),
            ];
        });
    }

    /**
     * Shuffle display order only — keep the same question set.
     *
     * @param  array<int, int>  $questionIds
     * @return array<int, int>
     */
    public function shuffleQuestionOrder(array $questionIds): array
    {
        $ids = array_values(array_map('intval', $questionIds));
        shuffle($ids);

        return $ids;
    }

    /**
     * Pick questions from the bank when a session has none stored yet.
     *
     * @return array<int, int>
     */
    public function initializeSessionQuestions(ListeningAssessment $assessment): array
    {
        $assessment->loadMissing('mediaItem');

        $mediaItem = $assessment->mediaItem;

        if ($mediaItem === null) {
            throw new RuntimeException('Assessment has no media item.');
        }

        $config = config("listening.assessments.{$assessment->type}");
        $sessionSize = (int) (is_array($config) ? ($config['question_count'] ?? $assessment->question_count) : $assessment->question_count);

        if ($sessionSize < 1) {
            throw new RuntimeException('Invalid session size.');
        }

        $bankCount = $mediaItem->listeningQuestions()->count();

        if (! $mediaItem->isQuestionBankReady() || $bankCount < $sessionSize) {
            throw new RuntimeException('Question bank is not ready or too small.');
        }

        $questionIds = $this->pickRandomQuestions($mediaItem, $sessionSize)->pluck('id')->all();

        $assessment->update([
            'question_ids' => $questionIds,
            'question_count' => count($questionIds),
        ]);

        return $questionIds;
    }

    public function findUnfinishedAssessment(MediaItem $mediaItem, User $user, string $type): ?ListeningAssessment
    {
        return ListeningAssessment::query()
            ->where('media_item_id', $mediaItem->id)
            ->where('user_id', $user->id)
            ->where('type', $type)
            ->where('status', ListeningAssessment::STATUS_READY)
            ->whereDoesntHave('attempts', fn ($query) => $query->where('user_id', $user->id))
            ->latest('created_at')
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function formatExistingSession(ListeningAssessment $assessment): array
    {
        $questions = $assessment->sessionQuestions();

        return [
            'assessment_id' => $assessment->id,
            'type' => $assessment->type,
            'title' => $assessment->title,
            'time_limit_minutes' => $assessment->time_limit_minutes,
            'question_count' => $questions->count(),
            'questions' => $this->formatQuestionsForClient($questions),
            'resumed' => true,
        ];
    }

    /**
     * @return Collection<int, ListeningQuestion>
     */
    private function pickRandomQuestions(MediaItem $mediaItem, int $sessionSize): Collection
    {
        return $mediaItem->listeningQuestions()
            ->inRandomOrder()
            ->limit($sessionSize)
            ->get()
            ->shuffle()
            ->values();
    }

    /**
     * @param  Collection<int, ListeningQuestion>  $questions
     * @return array<int, array<string, mixed>>
     */
    public function formatQuestionsForClient(Collection $questions): array
    {
        return $questions->values()->map(function (ListeningQuestion $question, int $index) {
            return [
                'id' => $question->id,
                'order' => $index + 1,
                'question_type' => $question->question_type,
                'prompt' => $question->prompt,
                'options' => $question->options,
                'audio_start_seconds' => $question->audio_start_seconds,
                'audio_end_seconds' => $question->audio_end_seconds,
            ];
        })->all();
    }
}
