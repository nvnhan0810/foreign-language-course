<?php

namespace App\Http\Controllers\Api\Agent;

use App\Http\Controllers\Controller;
use App\Models\Vocabulary;
use Flc\Shared\Application\CommandBus;
use Flc\Shared\Application\QueryBus;
use Flc\Vocabulary\Application\Command\DeleteUserVocabulary;
use Flc\Vocabulary\Application\Command\SaveUserVocabulary;
use Flc\Vocabulary\Application\Command\UpdateUserVocabulary;
use Flc\Vocabulary\Application\Query\GetUserVocabulary;
use Flc\Vocabulary\Application\Query\ListUserVocabularies;
use Flc\Vocabulary\Domain\UserVocabulary;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VocabularyController extends Controller
{
    public function __construct(
        private readonly CommandBus $commands,
        private readonly QueryBus $queries,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $items = $this->queries->ask(new ListUserVocabularies($request->user()->id));

        return response()->json(['data' => $items]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'word' => ['required', 'string', 'max:120'],
            'phonetic' => ['nullable', 'string', 'max:120'],
            'meanings' => ['nullable', 'array'],
            'meanings.*.part_of_speech' => ['nullable', 'string', 'max:40'],
            'meanings.*.definition' => ['required_with:meanings', 'string'],
            'meanings.*.example' => ['nullable', 'string'],
            'meanings.*.examples' => ['nullable', 'array'],
            'meanings.*.synonyms' => ['nullable', 'array'],
            'meanings.*.antonyms' => ['nullable', 'array'],
        ]);

        $result = $this->commands->dispatch(new SaveUserVocabulary(
            userId: $request->user()->id,
            word: $data['word'],
            phonetic: $data['phonetic'] ?? null,
            meanings: $data['meanings'] ?? null,
        ));

        if ($result === null) {
            return response()->json(['message' => 'Could not save word.'], 422);
        }

        /** @var UserVocabulary $vocabulary */
        $vocabulary = $result['vocabulary'];
        $status = ($result['created'] ?? false) ? 201 : 200;

        return response()->json([
            'data' => $vocabulary->toApiArray(),
            'created' => (bool) ($result['created'] ?? false),
            'backfilled' => (bool) ($result['backfilled'] ?? false),
        ], $status);
    }

    public function show(Request $request, Vocabulary $vocabulary): JsonResponse
    {
        $data = $this->queries->ask(new GetUserVocabulary($request->user()->id, $vocabulary->id));
        if ($data === null) {
            abort(403);
        }

        return response()->json(['data' => $data]);
    }

    public function update(Request $request, Vocabulary $vocabulary): JsonResponse
    {
        $data = $request->validate([
            'phonetic' => ['sometimes', 'nullable', 'string', 'max:120'],
            'meanings' => ['sometimes', 'array'],
            'meanings.*.part_of_speech' => ['nullable', 'string', 'max:40'],
            'meanings.*.definition' => ['required_with:meanings', 'string'],
            'meanings.*.example' => ['nullable', 'string'],
            'meanings.*.examples' => ['nullable', 'array'],
            'meanings.*.synonyms' => ['nullable', 'array'],
            'meanings.*.antonyms' => ['nullable', 'array'],
        ]);

        try {
            /** @var UserVocabulary $updated */
            $updated = $this->commands->dispatch(new UpdateUserVocabulary(
                $request->user()->id,
                $vocabulary->id,
                $data,
            ));
        } catch (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e) {
            throw $e;
        } catch (\RuntimeException) {
            abort(403);
        }

        return response()->json(['data' => $updated->toApiArray()]);
    }

    public function destroy(Request $request, Vocabulary $vocabulary): JsonResponse
    {
        $deleted = $this->commands->dispatch(new DeleteUserVocabulary(
            $request->user()->id,
            $vocabulary->id,
        ));

        if (! $deleted) {
            abort(403);
        }

        return response()->json(['message' => 'Word removed from your vocabulary.']);
    }
}
