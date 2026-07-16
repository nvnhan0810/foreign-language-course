<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Flc\Puzzle\Application\Query\GetNextScramblePuzzle;
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
}
