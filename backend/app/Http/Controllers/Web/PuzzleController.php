<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Flc\Puzzle\Application\Query\GetNextScramblePuzzle;
use Flc\Quiz\Application\Command\RecordQuizAttempt;
use Flc\Shared\Application\CommandBus;
use Flc\Shared\Application\QueryBus;
use Flc\Vocabulary\Application\Query\GetUserVocabulary;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class PuzzleController extends Controller
{
    public function __construct(
        private readonly QueryBus $queries,
        private readonly CommandBus $commands,
    ) {}

    public function index(Request $request): View|RedirectResponse
    {
        $mode = $request->query('mode');

        if (is_string($mode) && $mode !== '' && $mode !== 'scramble') {
            return redirect()
                ->route('user.home.puzzle')
                ->with('error', 'Coming soon.');
        }

        $this->clearScrambleSession();

        return view('user.puzzle.index');
    }

    public function exit(Request $request): RedirectResponse
    {
        $this->clearScrambleSession();

        return redirect()->route('user.home.quiz');
    }

    public function scramble(Request $request): View|RedirectResponse
    {
        if ($request->query('autostart') === '1' && ! session()->has('puzzle_scramble')) {
            return $this->nextScramble($request);
        }

        $reveal = session('puzzle_scramble_reveal');
        $startedAt = session('puzzle_scramble_started_at');
        $wordStartedAt = session('puzzle_scramble_word_started_at');
        $elapsed = session('puzzle_scramble_elapsed');

        if (session()->has('puzzle_scramble') && ! is_numeric($wordStartedAt)) {
            $wordStartedAt = now()->timestamp;
            session(['puzzle_scramble_word_started_at' => $wordStartedAt]);
        }

        return view('user.puzzle.scramble', [
            'puzzle' => session('puzzle_scramble'),
            'hint' => session('puzzle_scramble_hint'),
            'feedback' => session('puzzle_scramble_feedback'),
            'wasCorrect' => session('puzzle_scramble_was_correct'),
            'reveal' => is_array($reveal) ? $this->toViewModel($reveal) : null,
            'startedAt' => is_numeric($startedAt) ? (int) $startedAt : null,
            'wordStartedAt' => is_numeric($wordStartedAt) ? (int) $wordStartedAt : null,
            'elapsedSeconds' => is_numeric($elapsed) ? (int) $elapsed : null,
        ]);
    }

    public function nextScramble(Request $request): RedirectResponse
    {
        $puzzle = $this->queries->ask(new GetNextScramblePuzzle($request->user()->id));

        if (! $puzzle) {
            $this->clearScrambleSession();

            return redirect()
                ->route('user.home.puzzle.scramble')
                ->with('error', 'You need at least one saved single word (3–14 letters) to play Scramble. Add words in Vocabulary.');
        }

        $payload = [
            'puzzle_scramble' => $puzzle,
            'puzzle_scramble_hint' => null,
            'puzzle_scramble_feedback' => null,
            'puzzle_scramble_was_correct' => null,
            'puzzle_scramble_reveal' => null,
            'puzzle_scramble_elapsed' => null,
            'puzzle_scramble_word_started_at' => now()->timestamp,
        ];

        // Keep one continuous session clock across rounds until the player exits.
        if (! session()->has('puzzle_scramble_started_at')) {
            $payload['puzzle_scramble_started_at'] = now()->timestamp;
        }

        session($payload);

        return redirect()->route('user.home.puzzle.scramble');
    }

    public function hintScramble(Request $request): RedirectResponse
    {
        $puzzle = session('puzzle_scramble');

        if (! is_array($puzzle)) {
            return redirect()->route('user.home.puzzle.scramble');
        }

        if (session('puzzle_scramble_hint') !== null || session('puzzle_scramble_feedback') !== null) {
            return redirect()->route('user.home.puzzle.scramble');
        }

        session([
            'puzzle_scramble_hint' => [
                'definition' => $puzzle['hint_definition'] ?? '',
                'part_of_speech' => $puzzle['hint_part_of_speech'] ?? null,
            ],
        ]);

        return redirect()->route('user.home.puzzle.scramble');
    }

    public function answerScramble(Request $request): RedirectResponse
    {
        $puzzle = session('puzzle_scramble');

        if (! is_array($puzzle)) {
            return redirect()->route('user.home.puzzle.scramble');
        }

        if (session('puzzle_scramble_feedback') !== null) {
            return redirect()->route('user.home.puzzle.scramble');
        }

        $data = $request->validate([
            'answer' => ['required', 'string', 'max:40'],
        ]);

        $correctWord = strtolower(trim((string) ($puzzle['correct_word'] ?? '')));
        $answer = strtolower(trim($data['answer']));
        $correct = $correctWord !== '' && $answer === $correctWord;
        $vocabularyId = (int) ($puzzle['vocabulary_id'] ?? 0);

        $this->commands->dispatch(new RecordQuizAttempt(
            userId: $request->user()->id,
            vocabularyId: $vocabularyId,
            questionType: 'scramble',
            correct: $correct,
        ));

        $reveal = $this->queries->ask(new GetUserVocabulary(
            userId: $request->user()->id,
            vocabularyId: $vocabularyId,
        ));

        $startedAt = (int) session('puzzle_scramble_started_at', now()->timestamp);
        $elapsed = max(0, now()->timestamp - $startedAt);

        session([
            'puzzle_scramble_feedback' => $correct ? 'Correct!' : 'Incorrect. Answer: '.$correctWord,
            'puzzle_scramble_was_correct' => $correct,
            'puzzle_scramble_reveal' => is_array($reveal) ? $reveal : null,
            'puzzle_scramble_elapsed' => $elapsed,
        ]);

        return redirect()->route('user.home.puzzle.scramble');
    }

    private function clearScrambleSession(): void
    {
        session()->forget([
            'puzzle_scramble',
            'puzzle_scramble_hint',
            'puzzle_scramble_feedback',
            'puzzle_scramble_was_correct',
            'puzzle_scramble_reveal',
            'puzzle_scramble_started_at',
            'puzzle_scramble_word_started_at',
            'puzzle_scramble_elapsed',
        ]);
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function toViewModel(array $item): object
    {
        $examples = collect($item['examples'] ?? [])->map(fn (array $example) => (object) $example);

        return (object) array_merge($item, [
            'examples' => $examples instanceof Collection ? $examples : collect($examples),
        ]);
    }
}
