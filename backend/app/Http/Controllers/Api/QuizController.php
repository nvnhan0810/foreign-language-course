<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\QuizAttempt;
use App\Models\Vocabulary;
use App\Services\QuizSelectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QuizController extends Controller
{
    public function __construct(private readonly QuizSelectionService $quiz) {}

    public function next(Request $request): JsonResponse
    {
        $question = $this->quiz->nextQuestion($request->user());

        if (! $question) {
            return response()->json([
                'message' => 'Cần ít nhất 4 từ vựng đã lưu để tạo câu hỏi.',
            ], 422);
        }

        return response()->json(['data' => $question]);
    }

    public function attempt(Request $request): JsonResponse
    {
        $data = $request->validate([
            'vocabulary_id' => ['required', 'exists:vocabularies,id'],
            'question_type' => ['required', 'string', 'max:40'],
            'correct' => ['required', 'boolean'],
        ]);

        $vocabulary = Vocabulary::query()->findOrFail($data['vocabulary_id']);

        if ($vocabulary->user_id !== $request->user()->id) {
            abort(403);
        }

        QuizAttempt::query()->create([
            'vocabulary_id' => $vocabulary->id,
            'user_id' => $request->user()->id,
            'correct' => $data['correct'],
            'question_type' => $data['question_type'],
        ]);

        $vocabulary->times_quizzed++;
        $vocabulary->last_quizzed_at = now();

        if ($data['correct']) {
            $vocabulary->last_correct_at = now();
        }

        $vocabulary->save();

        return response()->json([
            'data' => [
                'vocabulary_id' => $vocabulary->id,
                'times_quizzed' => $vocabulary->times_quizzed,
            ],
        ]);
    }
}
