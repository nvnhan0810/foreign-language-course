<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\MediaItem;
use Flc\Listening\Application\Query\GetListeningSessionOptions;
use Flc\Shared\Application\QueryBus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MediaController extends Controller
{
    public function __construct(
        private readonly QueryBus $queries,
    ) {}

    public function index(Request $request): View
    {
        $items = $request->user()
            ->mediaItems()
            ->orderBy('next_listen_at')
            ->get();

        return view('user.media', ['items' => $items]);
    }

    public function show(Request $request, MediaItem $mediaItem): View
    {
        if ($mediaItem->user_id !== $request->user()->id) {
            abort(403);
        }

        return view('user.media-show', [
            'media' => $mediaItem,
            'sessionOptions' => $this->queries->ask(new GetListeningSessionOptions($mediaItem->id)),
        ]);
    }

    public function updateTranscript(Request $request, MediaItem $mediaItem): RedirectResponse
    {
        if ($mediaItem->user_id !== $request->user()->id) {
            abort(403);
        }

        $validated = $request->validate([
            'transcript' => ['nullable', 'string', 'max:100000'],
        ]);

        $mediaItem->update([
            'transcript' => $validated['transcript'] ?? null,
        ]);

        return redirect()
            ->route('user.home.media.show', $mediaItem)
            ->with('success', 'Đã lưu transcript.');
    }

    public function audio(Request $request, MediaItem $mediaItem): StreamedResponse|JsonResponse
    {
        if ($mediaItem->user_id !== $request->user()->id) {
            abort(403);
        }

        if (! $mediaItem->audio_path) {
            return response()->json(['message' => 'No audio file stored for this media item.'], 404);
        }

        $disk = Storage::disk($mediaItem->audio_disk);

        if (! $disk->exists($mediaItem->audio_path)) {
            return response()->json(['message' => 'Audio file not found.'], 404);
        }

        return $disk->response($mediaItem->audio_path);
    }
}
