<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\GameRecord;
use App\Models\User;
use Flc\Quiz\Application\Command\RecordQuizAttempt;
use Flc\Quiz\Application\Query\GetNextQuizQuestion;
use Flc\Shared\Application\CommandBus;
use Flc\Shared\Application\QueryBus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class QuizController extends Controller
{
    public function __construct(
        private readonly QueryBus $queries,
        private readonly CommandBus $commands,
    ) {}

    public function index(): View
    {
        $this->clearQuizSession();

        return view('user.quiz.hub');
    }

    public function play(Request $request): View|RedirectResponse
    {
        if ($request->query('autostart') === '1' && ! session()->has('quiz_question')) {
            return $this->next($request);
        }

        /** @var User $user */
        $user = $request->user();
        $celebrateRecord = session('quiz_celebrate_record');
        session()->forget('quiz_celebrate_record');

        return view('user.quiz.play', [
            'question' => session('quiz_question'),
            'feedback' => session('quiz_feedback'),
            'wasCorrect' => session('quiz_was_correct'),
            'sessionCorrect' => (int) session('quiz_session_correct', 0),
            'bestCorrect' => GameRecord::bestCorrectFor($user->id, GameRecord::GAME_QUIZ),
            'celebrateRecord' => $celebrateRecord,
        ]);
    }

    public function next(Request $request): RedirectResponse
    {
        $this->ensureQuizSessionStarted();

        $question = $this->queries->ask(new GetNextQuizQuestion($request->user()->id));

        if (! $question) {
            return redirect()->route('user.home.quiz.play')
                ->with('error', 'You need at least 4 saved words to generate a question.');
        }

        return redirect()->route('user.home.quiz.play')
            ->with('quiz_question', $question)
            ->with('quiz_feedback', null)
            ->with('quiz_was_correct', null);
    }

    public function answer(Request $request): RedirectResponse
    {
        $this->ensureQuizSessionStarted();

        $data = $request->validate([
            'vocabulary_id' => ['required', 'exists:vocabularies,id'],
            'question_type' => ['required', 'string', 'max:40'],
            'prompt' => ['required', 'string'],
            'correct_answer' => ['required', 'string'],
            'choice' => ['required', 'string'],
        ]);

        $correct = strtolower(trim($data['choice'])) === strtolower(trim($data['correct_answer']));

        $this->commands->dispatch(new RecordQuizAttempt(
            userId: $request->user()->id,
            vocabularyId: (int) $data['vocabulary_id'],
            questionType: $data['question_type'],
            correct: $correct,
        ));

        if ($correct) {
            $sessionCorrect = (int) session('quiz_session_correct', 0) + 1;
            session(['quiz_session_correct' => $sessionCorrect]);

            $bump = GameRecord::bumpIfBetter($request->user()->id, GameRecord::GAME_QUIZ, $sessionCorrect);
            if ($bump['is_new_record']) {
                session(['quiz_celebrate_record' => $sessionCorrect]);
            }
        }

        $question = [
            'vocabulary_id' => (int) $data['vocabulary_id'],
            'question_type' => $data['question_type'],
            'prompt' => $data['prompt'],
            'options' => session('quiz_question')['options'] ?? [],
            'correct_answer' => $data['correct_answer'],
        ];

        return redirect()->route('user.home.quiz.play')
            ->with('quiz_question', $question)
            ->with('quiz_feedback', $correct ? 'Correct!' : 'Incorrect. Answer: '.$data['correct_answer'])
            ->with('quiz_was_correct', $correct);
    }

    private function ensureQuizSessionStarted(): void
    {
        if (session()->has('quiz_session_started')) {
            return;
        }

        session([
            'quiz_session_started' => true,
            'quiz_session_correct' => 0,
            'quiz_celebrate_record' => null,
        ]);
    }

    private function clearQuizSession(): void
    {
        session()->forget([
            'quiz_question',
            'quiz_feedback',
            'quiz_was_correct',
            'quiz_session_started',
            'quiz_session_correct',
            'quiz_celebrate_record',
        ]);
    }
}
