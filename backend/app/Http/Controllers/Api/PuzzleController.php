<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Flc\Puzzle\Application\Query\GetNextScramblePuzzle;
use Flc\Puzzle\Application\Query\GetNextWordlePuzzle;
use Flc\Puzzle\Domain\WordleGrader;
use Flc\Puzzle\Domain\WordleKeyboardBuilder;
use Flc\Puzzle\Application\Query\GetScrambleHint;
use Flc\Quiz\Application\Command\RecordQuizAttempt;
use Flc\Shared\Application\CommandBus;
use Flc\Shared\Application\QueryBus;
use Flc\Vocabulary\Application\Query\GetUserVocabulary;
use Flc\Vocabulary\Application\Repository\UserVocabularyRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class PuzzleController extends Controller
{
    public function __construct(
        private readonly QueryBus $queries,
        private readonly CommandBus $commands,
        private readonly UserVocabularyRepository $vocabularies,
    ) {}

    public function nextScramble(Request $request): JsonResponse
    {
        $puzzle = $this->queries->ask(new GetNextScramblePuzzle($request->user()->id));

        if (! $puzzle) {
            return response()->json([
                'message' => 'You need at least one saved single word (3–14 letters) to play Scramble.',
            ], 422);
        }

        return response()->json([
            'data' => [
                'vocabulary_id' => $puzzle['vocabulary_id'],
                'mode' => $puzzle['mode'],
                'scrambled' => $puzzle['scrambled'],
                'word_length' => $puzzle['word_length'],
            ],
        ]);
    }

    public function hintScramble(Request $request): JsonResponse
    {
        $data = $request->validate([
            'vocabulary_id' => ['required', 'integer', 'exists:vocabularies,id'],
        ]);

        $hint = $this->queries->ask(new GetScrambleHint(
            userId: $request->user()->id,
            vocabularyId: (int) $data['vocabulary_id'],
        ));

        return response()->json(['data' => $hint]);
    }

    public function attemptScramble(Request $request): JsonResponse
    {
        $data = $request->validate([
            'vocabulary_id' => ['required', 'integer', 'exists:vocabularies,id'],
            'answer' => ['required', 'string', 'max:40'],
        ]);

        $vocabulary = $this->vocabularies->findForUser(
            $request->user()->id,
            (int) $data['vocabulary_id'],
        );

        if ($vocabulary === null) {
            throw new AccessDeniedHttpException();
        }

        $correctWord = strtolower(trim($vocabulary->word));
        $answer = strtolower(trim($data['answer']));
        $correct = $answer === $correctWord;

        $attempt = $this->commands->dispatch(new RecordQuizAttempt(
            userId: $request->user()->id,
            vocabularyId: (int) $data['vocabulary_id'],
            questionType: 'scramble',
            correct: $correct,
        ));

        $entry = $this->queries->ask(new GetUserVocabulary(
            userId: $request->user()->id,
            vocabularyId: (int) $data['vocabulary_id'],
        ));

        return response()->json([
            'data' => [
                'correct' => $correct,
                'correct_word' => $correctWord,
                'attempt' => $attempt,
                'entry' => $entry,
            ],
        ]);
    }

    public function nextWordle(Request $request): JsonResponse
    {
        $puzzle = $this->queries->ask(new GetNextWordlePuzzle($request->user()->id));

        if (! $puzzle) {
            return response()->json([
                'message' => 'You need at least one saved 5-letter word to play Wordle.',
            ], 422);
        }

        return response()->json([
            'data' => [
                'vocabulary_id' => $puzzle['vocabulary_id'],
                'mode' => $puzzle['mode'],
                'word_length' => $puzzle['word_length'],
                'max_guesses' => $puzzle['max_guesses'],
                'keyboard_letters' => $puzzle['keyboard_letters'],
            ],
        ]);
    }

    public function guessWordle(Request $request): JsonResponse
    {
        $data = $request->validate([
            'vocabulary_id' => ['required', 'integer', 'exists:vocabularies,id'],
            'guess' => ['required', 'string', 'max:10'],
            'guesses_used' => ['required', 'integer', 'min:1', 'max:'.WordleGrader::MAX_GUESSES],
        ]);

        $vocabulary = $this->vocabularies->findForUser(
            $request->user()->id,
            (int) $data['vocabulary_id'],
        );

        if ($vocabulary === null) {
            throw new AccessDeniedHttpException();
        }

        $guess = strtolower(trim($data['guess']));
        $correctWord = strtolower(trim($vocabulary->word));
        $vocabularyId = (int) $data['vocabulary_id'];
        $keyboard = WordleKeyboardBuilder::build($correctWord, null, $vocabularyId);

        if (! WordleGrader::isValidGuess($guess)) {
            return response()->json([
                'message' => 'Enter a 5-letter word (a–z only).',
            ], 422);
        }

        if (! WordleKeyboardBuilder::isGuessAllowed($guess, $keyboard)) {
            return response()->json([
                'message' => 'Use only the letters on the keyboard.',
            ], 422);
        }

        $tiles = WordleGrader::grade($correctWord, $guess);
        $won = $guess === $correctWord;
        $finished = $won || (int) $data['guesses_used'] >= WordleGrader::MAX_GUESSES;

        $attempt = null;
        $entry = null;

        if ($finished) {
            $attempt = $this->commands->dispatch(new RecordQuizAttempt(
                userId: $request->user()->id,
                vocabularyId: (int) $data['vocabulary_id'],
                questionType: 'wordle',
                correct: $won,
            ));

            $entry = $this->queries->ask(new GetUserVocabulary(
                userId: $request->user()->id,
                vocabularyId: (int) $data['vocabulary_id'],
            ));
        }

        return response()->json([
            'data' => [
                'tiles' => $tiles,
                'won' => $won,
                'finished' => $finished,
                'correct_word' => $finished ? $correctWord : null,
                'attempt' => $attempt,
                'entry' => $entry,
            ],
        ]);
    }
}
