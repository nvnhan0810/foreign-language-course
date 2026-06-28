<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ListeningAssessment;
use App\Models\ListeningAttempt;
use App\Models\ListeningQuestion;
use App\Models\MediaItem;
use App\Services\ListeningSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class ListeningAssessmentController extends Controller
{
    public function __construct(
        private readonly ListeningSessionService $sessionService,
    ) {}

    public function show(Request $request, ListeningAssessment $listeningAssessment): JsonResponse
    {
        $this->authorizeAssessment($request, $listeningAssessment);

        $listeningAssessment->load(['mediaItem:id,title,url,type,source_id,audio_path,analysis_status,language']);

        return response()->json(['data' => $listeningAssessment]);
    }

    public function questions(Request $request, ListeningAssessment $listeningAssessment): JsonResponse
    {
        $this->authorizeAssessment($request, $listeningAssessment);

        if ($listeningAssessment->status !== ListeningAssessment::STATUS_READY) {
            return response()->json([
                'message' => 'Assessment is not ready yet.',
                'data' => ['status' => $listeningAssessment->status],
            ], 422);
        }

        $questions = $this->sessionService->formatQuestionsForClient(
            $listeningAssessment->sessionQuestions()
        );

        return response()->json([
            'data' => [
                'assessment_id' => $listeningAssessment->id,
                'type' => $listeningAssessment->type,
                'title' => $listeningAssessment->title,
                'time_limit_minutes' => $listeningAssessment->time_limit_minutes,
                'question_count' => count($questions),
                'questions' => $questions,
            ],
        ]);
    }

    public function submitAttempt(Request $request, ListeningAssessment $listeningAssessment): JsonResponse
    {
        $this->authorizeAssessment($request, $listeningAssessment);

        if ($listeningAssessment->status !== ListeningAssessment::STATUS_READY) {
            return response()->json(['message' => 'Assessment is not ready yet.'], 422);
        }

        $data = $request->validate([
            'answers' => ['required', 'array', 'min:1'],
            'answers.*.question_id' => ['required', 'integer', 'exists:listening_questions,id'],
            'answers.*.answer' => ['required', 'string'],
            'started_at' => ['nullable', 'date'],
        ]);

        $questions = $listeningAssessment->sessionQuestions()->keyBy('id');
        $score = 0;
        $results = [];

        foreach ($data['answers'] as $answer) {
            $question = $questions->get($answer['question_id']);

            if (! $question || $question->media_item_id !== $listeningAssessment->media_item_id) {
                abort(422, 'Invalid question for this session.');
            }

            $isCorrect = $this->isAnswerCorrect($question, $answer['answer']);

            if ($isCorrect) {
                $score++;
            }

            $results[] = [
                'question_id' => $question->id,
                'answer' => $answer['answer'],
                'correct' => $isCorrect,
                'correct_answer' => $question->correct_answer,
                'explanation' => $question->explanation,
            ];
        }

        $total = $questions->count();
        $percentage = $total > 0 ? round(($score / $total) * 100, 2) : 0;

        $attempt = ListeningAttempt::query()->create([
            'listening_assessment_id' => $listeningAssessment->id,
            'media_item_id' => $listeningAssessment->media_item_id,
            'type' => $listeningAssessment->type,
            'user_id' => $request->user()->id,
            'score' => $score,
            'total' => $total,
            'percentage' => $percentage,
            'answers' => $results,
            'started_at' => $data['started_at'] ?? now(),
            'completed_at' => now(),
        ]);

        return response()->json([
            'data' => [
                'attempt_id' => $attempt->id,
                'score' => $score,
                'total' => $total,
                'percentage' => $percentage,
                'passed' => $percentage >= $this->passThreshold($listeningAssessment->type),
                'results' => $results,
            ],
        ]);
    }

    public function attempts(Request $request, ListeningAssessment $listeningAssessment): JsonResponse
    {
        $this->authorizeAssessment($request, $listeningAssessment);

        $attempts = $listeningAssessment->attempts()
            ->where('user_id', $request->user()->id)
            ->orderByDesc('completed_at')
            ->get(['id', 'score', 'total', 'percentage', 'started_at', 'completed_at']);

        return response()->json(['data' => $attempts]);
    }

    public function startSession(Request $request, MediaItem $mediaItem): JsonResponse
    {
        $this->authorizeMedia($request, $mediaItem);

        $data = $request->validate([
            'type' => ['required', 'in:quiz,test,exam'],
        ]);

        try {
            $session = $this->sessionService->startSession(
                $mediaItem,
                $request->user(),
                $data['type']
            );
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $session], 201);
    }

    public function sessionOptions(Request $request, MediaItem $mediaItem): JsonResponse
    {
        $this->authorizeMedia($request, $mediaItem);

        return response()->json([
            'data' => $this->sessionService->sessionOptions($mediaItem),
        ]);
    }

    private function isAnswerCorrect(ListeningQuestion $question, string $answer): bool
    {
        $normalizedAnswer = $this->normalize($answer);
        $normalizedCorrect = $this->normalize($question->correct_answer);

        if ($normalizedAnswer === $normalizedCorrect) {
            return true;
        }

        if ($question->question_type === ListeningQuestion::TYPE_TRUE_FALSE) {
            $answerIsTrue = in_array($normalizedAnswer, ['true', 't', 'yes', '1'], true);
            $correctIsTrue = in_array($normalizedCorrect, ['true', 't', 'yes', '1'], true);
            $answerIsFalse = in_array($normalizedAnswer, ['false', 'f', 'no', '0'], true);
            $correctIsFalse = in_array($normalizedCorrect, ['false', 'f', 'no', '0'], true);

            return ($answerIsTrue && $correctIsTrue) || ($answerIsFalse && $correctIsFalse);
        }

        return false;
    }

    private function normalize(string $value): string
    {
        return strtolower(trim(preg_replace('/\s+/', ' ', $value) ?? ''));
    }

    private function passThreshold(string $type): int
    {
        return match ($type) {
            ListeningAssessment::TYPE_QUIZ => 60,
            ListeningAssessment::TYPE_TEST => 70,
            ListeningAssessment::TYPE_EXAM => 75,
            default => 70,
        };
    }

    private function authorizeAssessment(Request $request, ListeningAssessment $assessment): void
    {
        if ($assessment->user_id !== $request->user()->id) {
            abort(403);
        }
    }

    private function authorizeMedia(Request $request, MediaItem $mediaItem): void
    {
        if ($mediaItem->user_id !== $request->user()->id) {
            abort(403);
        }
    }
}
