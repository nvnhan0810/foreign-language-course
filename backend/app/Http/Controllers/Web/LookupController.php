<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Vocabulary;
use App\Models\VocabularyExample;
use App\Services\DictionaryService;
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
            return redirect()->route('user.home.lookup')
                ->with('success', 'Từ đã có trong danh sách.')
                ->with('lookup_saved', true);
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

        return redirect()->route('user.home.lookup')
            ->with('success', 'Đã lưu từ.')
            ->with('lookup_saved', true);
    }
}
