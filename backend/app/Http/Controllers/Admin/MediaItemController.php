<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MediaItem;
use App\Services\MediaScheduleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MediaItemController extends Controller
{
    public function index(Request $request): View
    {
        $query = MediaItem::query()->with('user')->latest();

        if ($search = $request->string('q')->trim()->toString()) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', '%'.$search.'%')
                    ->orWhere('url', 'like', '%'.$search.'%');
            });
        }

        return view('admin.media-items.index', [
            'mediaItems' => $query->paginate(25)->withQueryString(),
            'search' => $search ?? '',
        ]);
    }

    public function edit(MediaItem $mediaItem): View
    {
        $mediaItem->load('user');

        return view('admin.media-items.edit', compact('mediaItem'));
    }

    public function update(Request $request, MediaItem $mediaItem, MediaScheduleService $schedule): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'url' => ['required', 'url', 'max:2048'],
            'type' => ['required', 'in:audio,youtube'],
            'frequency' => ['required', 'in:daily,weekly,monthly'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $frequencyChanged = $mediaItem->frequency !== $data['frequency'];

        $mediaItem->update([
            ...$data,
            'is_active' => $request->boolean('is_active'),
        ]);

        if ($frequencyChanged) {
            $mediaItem->next_listen_at = $schedule->initialNextListenAt($data['frequency']);
            $mediaItem->save();
        }

        return redirect()->route('admin.media-items.index')
            ->with('success', 'Đã cập nhật media.');
    }

    public function destroy(MediaItem $mediaItem): RedirectResponse
    {
        $mediaItem->delete();

        return redirect()->route('admin.media-items.index')
            ->with('success', 'Đã xóa media.');
    }
}
