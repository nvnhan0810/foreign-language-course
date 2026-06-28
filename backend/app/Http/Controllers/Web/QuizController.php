<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\QuizAttempt;
use App\Models\Vocabulary;
use App\Services\QuizSelectionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class QuizController extends Controller
{
    public function __construct(private readonly QuizSelectionService $quiz) {}

    public function index(Request $request): View|RedirectResponse
    {
        if ($request->query('autostart') === '1' && ! session()->has('quiz_question')) {
            return $this->next($request);
        }

        return view('user.quiz', [
            'question' => session('quiz_question'),
            'feedback' => session('quiz_feedback'),
            'wasCorrect' => session('quiz_was_correct'),
        ]);
    }

    public function next(Request $request): RedirectResponse
    {
        $question = $this->quiz->nextQuestion($request->user());

        if (! $question) {
            return redirect()->route('user.home.quiz')
                ->with('error', 'Cần ít nhất 4 từ vựng đã lưu để tạo câu hỏi.');
        }

        return redirect()->route('user.home.quiz')
            ->with('quiz_question', $question)
            ->with('quiz_feedback', null)
            ->with('quiz_was_correct', null);
    }

    public function answer(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'vocabulary_id' => ['required', 'exists:vocabularies,id'],
            'question_type' => ['required', 'string', 'max:40'],
            'prompt' => ['required', 'string'],
            'correct_answer' => ['required', 'string'],
            'choice' => ['required', 'string'],
        ]);

        $vocabulary = Vocabulary::query()->findOrFail($data['vocabulary_id']);

        if ($vocabulary->user_id !== $request->user()->id) {
            abort(403);
        }

        $correct = strtolower(trim($data['choice'])) === strtolower(trim($data['correct_answer']));

        QuizAttempt::query()->create([
            'vocabulary_id' => $vocabulary->id,
            'user_id' => $request->user()->id,
            'correct' => $correct,
            'question_type' => $data['question_type'],
        ]);

        $vocabulary->times_quizzed++;
        $vocabulary->last_quizzed_at = now();

        if ($correct) {
            $vocabulary->last_correct_at = now();
        }

        $vocabulary->save();

        $question = [
            'vocabulary_id' => $vocabulary->id,
            'question_type' => $data['question_type'],
            'prompt' => $data['prompt'],
            'options' => session('quiz_question')['options'] ?? [],
            'correct_answer' => $data['correct_answer'],
        ];

        return redirect()->route('user.home.quiz')
            ->with('quiz_question', $question)
            ->with('quiz_feedback', $correct ? 'Đúng!' : 'Sai. Đáp án: '.$data['correct_answer'])
            ->with('quiz_was_correct', $correct);
    }
}
