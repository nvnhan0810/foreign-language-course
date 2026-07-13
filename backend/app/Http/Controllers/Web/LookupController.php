<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Flc\Dictionary\Application\Query\LookupWord;
use Flc\Shared\Application\CommandBus;
use Flc\Shared\Application\QueryBus;
use Flc\Shared\Support\Text;
use Flc\Vocabulary\Application\Command\SaveUserVocabulary;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LookupController extends Controller
{
    public function __construct(
        private readonly QueryBus $queries,
        private readonly CommandBus $commands,
    ) {}

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
        $word = Text::lower(preg_replace('/\s+/', ' ', $text) ?? $text);

        if (! preg_match('/^[a-z][a-z\s\'-]*$/i', $word)) {
            return back()->withInput()->with('error', 'Enter a valid English word or phrase.');
        }

        $result = $this->queries->ask(new LookupWord($word));

        if (! $result) {
            return back()->withInput()->with('error', 'Word not found.');
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

        $word = Text::lower(trim($data['word']));
        $meanings = is_array($data['meanings'] ?? null) ? $data['meanings'] : null;

        $result = $this->commands->dispatch(new SaveUserVocabulary(
            userId: $request->user()->id,
            word: $word,
            phonetic: $data['phonetic'] ?? null,
            meanings: $meanings,
        ));

        if ($result === null) {
            return $this->redirectToLookupAfterSave($data, 'Could not save word.');
        }

        if ($result['created'] ?? false) {
            $lookup = $this->queries->ask(new LookupWord($word));

            return $this->redirectToLookupAfterSave($data, 'Word saved.', is_array($lookup) ? $lookup : null);
        }

        if ($result['backfilled'] ?? false) {
            $lookup = $this->queries->ask(new LookupWord($word));

            return $this->redirectToLookupAfterSave(
                $data,
                'Updated synonyms and antonyms for this saved word.',
                is_array($lookup) ? $lookup : null,
            );
        }

        return $this->redirectToLookupAfterSave($data, 'This word is already in your list.');
    }

    /**
     * @param  array{word: string, phonetic?: string|null, meanings?: array<int, mixed>|null}  $data
     * @param  array<string, mixed>|null  $lookup
     */
    private function redirectToLookupAfterSave(array $data, string $message, ?array $lookup = null): RedirectResponse
    {
        $lookup ??= $this->queries->ask(new LookupWord(Text::lower(trim($data['word']))));

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
        $result = $this->queries->ask(new LookupWord($word));

        if (! $result || empty($result['audio_url'])) {
            abort(404, 'No pronunciation audio for this word.');
        }

        if ($request->expectsJson()) {
            return response()->json(['audio_url' => $result['audio_url']]);
        }

        return redirect()->away($result['audio_url']);
    }
}
