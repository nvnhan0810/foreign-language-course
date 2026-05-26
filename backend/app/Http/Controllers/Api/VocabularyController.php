<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Vocabulary;
use App\Models\VocabularyExample;
use App\Services\DictionaryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class VocabularyController extends Controller
{
    public function __construct(private readonly DictionaryService $dictionary) {}

    public function index(Request $request): JsonResponse
    {
        $items = $request->user()
            ->vocabularies()
            ->with('examples')
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['data' => $items]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'word' => ['required', 'string', 'max:120'],
            'phonetic' => ['nullable', 'string', 'max:120'],
            'meanings' => ['nullable', 'array'],
        ]);

        $word = Str::lower(trim($data['word']));
        $existing = $request->user()->vocabularies()->where('word', $word)->first();

        if ($existing) {
            return response()->json(['data' => $existing->load('examples')], 200);
        }

        $lookup = $this->dictionary->lookup($word);
        $meanings = $data['meanings'] ?? $lookup['meanings'] ?? [];

        $vocabulary = $request->user()->vocabularies()->create([
            'word' => $word,
            'phonetic' => $data['phonetic'] ?? $lookup['phonetic'] ?? null,
            'meanings' => $meanings,
        ]);

        foreach (array_slice($meanings, 0, 5) as $meaning) {
            if (! empty($meaning['example'])) {
                VocabularyExample::query()->create([
                    'vocabulary_id' => $vocabulary->id,
                    'example' => $meaning['example'],
                    'definition_ref' => $meaning['definition'] ?? null,
                ]);
            }
        }

        return response()->json(['data' => $vocabulary->load('examples')], 201);
    }

    public function show(Request $request, Vocabulary $vocabulary): JsonResponse
    {
        $this->authorizeVocabulary($request, $vocabulary);

        return response()->json(['data' => $vocabulary->load('examples')]);
    }

    public function update(Request $request, Vocabulary $vocabulary): JsonResponse
    {
        $this->authorizeVocabulary($request, $vocabulary);

        $data = $request->validate([
            'phonetic' => ['sometimes', 'nullable', 'string', 'max:120'],
            'meanings' => ['sometimes', 'array'],
        ]);

        $vocabulary->update($data);

        return response()->json(['data' => $vocabulary->fresh('examples')]);
    }

    public function destroy(Request $request, Vocabulary $vocabulary): JsonResponse
    {
        $this->authorizeVocabulary($request, $vocabulary);
        $vocabulary->delete();

        return response()->json(['message' => 'Đã xóa từ.']);
    }

    private function authorizeVocabulary(Request $request, Vocabulary $vocabulary): void
    {
        if ($vocabulary->user_id !== $request->user()->id) {
            abort(403);
        }
    }
}
