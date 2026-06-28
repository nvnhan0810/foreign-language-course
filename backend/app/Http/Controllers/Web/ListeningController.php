<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ListeningAssessment;
use App\Models\ListeningAttempt;
use App\Models\MediaItem;
use App\Services\ListeningSessionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class ListeningController extends Controller
{
    public function __construct(
        private readonly ListeningSessionService $sessionService,
    ) {}

    public function start(Request $request, MediaItem $mediaItem): RedirectResponse
    {
        if ($mediaItem->user_id !== $request->user()->id) {
            abort(403);
        }

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
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('user.listening.show', $session['assessment_id']);
    }

    public function show(Request $request, ListeningAssessment $listeningAssessment): View|RedirectResponse
    {
        $this->authorizeAssessment($request, $listeningAssessment);

        if ($listeningAssessment->status !== ListeningAssessment::STATUS_READY) {
            return redirect()->back()->with('error', 'Bài kiểm tra chưa sẵn sàng.');
        }

        $listeningAssessment->load('mediaItem');

        $questions = $listeningAssessment->sessionQuestions();

        return view('user.listening', [
            'assessment' => $listeningAssessment,
            'questions' => $questions,
            'result' => session('listening_result'),
        ]);
    }

    public function submit(Request $request, ListeningAssessment $listeningAssessment): RedirectResponse
    {
        $this->authorizeAssessment($request, $listeningAssessment);

        if ($listeningAssessment->status !== ListeningAssessment::STATUS_READY) {
            return redirect()->back()->with('error', 'Bài kiểm tra chưa sẵn sàng.');
        }

        $data = $request->validate([
            'answers' => ['required', 'array', 'min:1'],
            'answers.*' => ['required', 'string'],
        ]);

        $questions = $listeningAssessment->sessionQuestions()->keyBy('id');
        $score = 0;
        $results = [];

        foreach ($data['answers'] as $questionId => $answer) {
            $question = $questions->get((int) $questionId);

            if (! $question) {
                continue;
            }

            $isCorrect = $this->isAnswerCorrect($question, $answer);

            if ($isCorrect) {
                $score++;
            }

            $results[] = [
                'question_id' => $question->id,
                'correct' => $isCorrect,
            ];
        }

        $total = $questions->count();
        $percentage = $total > 0 ? round(($score / $total) * 100, 1) : 0;

        ListeningAttempt::query()->create([
            'listening_assessment_id' => $listeningAssessment->id,
            'media_item_id' => $listeningAssessment->media_item_id,
            'type' => $listeningAssessment->type,
            'user_id' => $request->user()->id,
            'score' => $score,
            'total' => $total,
            'percentage' => $percentage,
            'answers' => $results,
            'completed_at' => now(),
        ]);

        return redirect()->route('user.listening.show', $listeningAssessment)
            ->with('listening_result', [
                'score' => $score,
                'total' => $total,
                'percentage' => $percentage,
            ])
            ->with('success', 'Đã nộp bài.');
    }

    private function authorizeAssessment(Request $request, ListeningAssessment $listeningAssessment): void
    {
        if ($listeningAssessment->user_id !== $request->user()->id) {
            abort(403);
        }
    }

    private function isAnswerCorrect($question, string $answer): bool
    {
        $normalized = strtolower(trim($answer));
        $correct = strtolower(trim((string) $question->correct_answer));

        return $normalized === $correct;
    }
}
