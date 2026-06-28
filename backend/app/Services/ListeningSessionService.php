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
            $selected = $mediaItem->listeningQuestions()
                ->inRandomOrder()
                ->limit($sessionSize)
                ->get()
                ->values();

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
