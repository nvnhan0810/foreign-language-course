<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Flc\Dictionary\Application\Query\LookupWord;
use Flc\Shared\Application\QueryBus;
use Flc\Shared\Support\Text;
use Flc\Vocabulary\Application\Query\FindUserVocabularyByWord;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LookupController extends Controller
{
    public function __construct(
        private readonly QueryBus $queries,
    ) {}

    public function index(Request $request): Response
    {
        $prefill = trim((string) $request->query('q', ''));

        return Inertia::render('Learn/Index', [
            'prefill' => $prefill,
        ]);
    }

    /**
     * Open a related word: prefer saved vocabulary detail when available, else word chat.
     */
    public function openWord(Request $request, string $word): RedirectResponse
    {
        $text = trim(urldecode($word));
        $normalized = Text::lower(preg_replace('/\s+/', ' ', $text) ?? $text);

        if ($normalized === '' || ! preg_match('/^[a-z][a-z\s\'-]*$/i', $normalized)) {
            return redirect()->route('user.home.lookup')
                ->with('error', 'Enter a valid English word or phrase.');
        }

        $preferDetail = $request->boolean('detail', true);

        /** @var array<string, mixed>|null $savedVocab */
        $savedVocab = $this->queries->ask(new FindUserVocabularyByWord(
            userId: (int) $request->user()->id,
            word: $normalized,
        ));

        if ($preferDetail && is_array($savedVocab) && ! empty($savedVocab['id'])) {
            return redirect()->route('user.home.vocab.show', $savedVocab['id']);
        }

        return redirect()->route('user.home.lookup', ['q' => $text]);
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
