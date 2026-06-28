<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\MediaItem;
use App\Services\ListeningSessionService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MediaController extends Controller
{
    public function __construct(
        private readonly ListeningSessionService $sessionService,
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
            'sessionOptions' => $this->sessionService->sessionOptions($mediaItem),
        ]);
    }
}
