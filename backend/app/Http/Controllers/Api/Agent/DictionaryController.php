<?php

namespace App\Http\Controllers\Api\Agent;

use App\Http\Controllers\Controller;
use Flc\Dictionary\Application\Command\CurateDictionaryEntry;
use Flc\Dictionary\Application\Query\LookupWord;
use Flc\Dictionary\Domain\DictionaryEntry;
use Flc\Shared\Application\CommandBus;
use Flc\Shared\Application\QueryBus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DictionaryController extends Controller
{
    public function __construct(
        private readonly QueryBus $queries,
        private readonly CommandBus $commands,
    ) {}

    public function show(string $word): JsonResponse
    {
        $result = $this->queries->ask(new LookupWord($word));

        if (! $result) {
            return response()->json(['message' => 'Word not found.'], 404);
        }

        return response()->json($result);
    }

    public function curate(Request $request, string $word): JsonResponse
    {
        $data = $this->validatedEntry($request, $word);
        $entry = $this->commands->dispatch(new CurateDictionaryEntry($data['word'], $data));

        assert($entry instanceof DictionaryEntry);

        return response()->json([
            'data' => $entry->toClientPayload(),
            'message' => 'Dictionary entry curated.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedEntry(Request $request, string $word): array
    {
        $data = $request->validate([
            'word' => ['nullable', 'string', 'max:255'],
            'phonetic' => ['nullable', 'string', 'max:120'],
            'audio_url' => ['nullable', 'string', 'max:2000'],
            'meanings' => ['required', 'array', 'min:1'],
            'meanings.*.part_of_speech' => ['nullable', 'string', 'max:40'],
            'meanings.*.definition' => ['required', 'string'],
            'meanings.*.example' => ['nullable', 'string'],
            'meanings.*.examples' => ['nullable', 'array'],
            'meanings.*.examples.*' => ['string'],
            'meanings.*.synonyms' => ['nullable', 'array'],
            'meanings.*.synonyms.*' => ['string'],
            'meanings.*.antonyms' => ['nullable', 'array'],
            'meanings.*.antonyms.*' => ['string'],
            'synonyms' => ['nullable', 'array'],
            'synonyms.*' => ['string'],
            'antonyms' => ['nullable', 'array'],
            'antonyms.*' => ['string'],
        ]);

        $meanings = [];
        foreach ($data['meanings'] as $meaning) {
            $definition = trim((string) ($meaning['definition'] ?? ''));
            if ($definition === '') {
                continue;
            }

            $examples = DictionaryEntry::stringList($meaning['examples'] ?? []);
            if ($examples === [] && ! empty($meaning['example'])) {
                $examples = [trim((string) $meaning['example'])];
            }

            $meanings[] = [
                'part_of_speech' => ($meaning['part_of_speech'] ?? null) ?: null,
                'definition' => $definition,
                'examples' => $examples,
                'synonyms' => DictionaryEntry::stringList($meaning['synonyms'] ?? []),
                'antonyms' => DictionaryEntry::stringList($meaning['antonyms'] ?? []),
            ];
        }

        if ($meanings === []) {
            abort(422, 'At least one meaning with a definition is required.');
        }

        $resolvedWord = trim((string) ($data['word'] ?? $word));

        return [
            'word' => $resolvedWord !== '' ? $resolvedWord : $word,
            'phonetic' => $data['phonetic'] ?? null,
            'audio_url' => $data['audio_url'] ?? null,
            'meanings' => $meanings,
            'synonyms' => DictionaryEntry::stringList($data['synonyms'] ?? []),
            'antonyms' => DictionaryEntry::stringList($data['antonyms'] ?? []),
        ];
    }
}
