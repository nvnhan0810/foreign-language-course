<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Vocabulary;
use App\Models\VocabularyExample;
use App\Services\DictionaryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class LookupController extends Controller
{
    public function __construct(private readonly DictionaryService $dictionary) {}

    public function index(Request $request): View
    {
        return view('user.lookup', [
            'result' => session('lookup_result'),
            'word' => session('lookup_word', ''),
            'saved' => session('lookup_saved', false),
        ]);
    }

    public function lookup(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'word' => ['required', 'string', 'max:500'],
        ]);

        $text = trim($data['word']);
        $word = Str::lower(preg_replace('/\s+/', ' ', $text) ?? $text);

        if (! preg_match('/^[a-z][a-z\s\'-]*$/i', $word)) {
            return back()->withInput()->with('error', 'Nhập từ/câu tiếng Anh hợp lệ.');
        }

        $result = $this->dictionary->lookup($word);

        if (! $result) {
            return back()->withInput()->with('error', 'Không tìm thấy từ.');
        }

        return redirect()->route('user.home.lookup')
            ->with('lookup_word', $text)
            ->with('lookup_result', $result)
            ->with('lookup_saved', false);
    }

    public function save(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'word' => ['required', 'string', 'max:120'],
            'phonetic' => ['nullable', 'string', 'max:120'],
            'meanings' => ['nullable', 'array'],
        ]);

        $word = Str::lower(trim($data['word']));
        $existing = $request->user()->vocabularies()->where('word', $word)->first();

        if ($existing) {
            return $this->redirectToLookupAfterSave($data, 'Từ đã có trong danh sách.');
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

        return $this->redirectToLookupAfterSave($data, 'Đã lưu từ.', $lookup);
    }

    /**
     * @param  array{word: string, phonetic?: string|null, meanings?: array<int, mixed>|null}  $data
     * @param  array<string, mixed>|null  $lookup
     */
    private function redirectToLookupAfterSave(array $data, string $message, ?array $lookup = null): RedirectResponse
    {
        $lookup ??= $this->dictionary->lookup(Str::lower(trim($data['word'])));

        if ($lookup === null) {
            $lookup = [
                'word' => $data['word'],
                'phonetic' => $data['phonetic'] ?? null,
                'audio_url' => null,
                'meanings' => $data['meanings'] ?? [],
            ];
        }

        return redirect()->route('user.home.lookup')
            ->with('success', $message)
            ->with('lookup_saved', true)
            ->with('lookup_word', $data['word'])
            ->with('lookup_result', $lookup);
    }

    public function pronounce(Request $request, string $word): JsonResponse|RedirectResponse
    {
        $result = $this->dictionary->lookup($word);

        if (! $result || empty($result['audio_url'])) {
            abort(404, 'Không có audio phát âm cho từ này.');
        }

        if ($request->expectsJson()) {
            return response()->json(['audio_url' => $result['audio_url']]);
        }

        return redirect()->away($result['audio_url']);
    }
}
