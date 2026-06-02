<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ListeningAssessment;
use App\Models\ListeningAssessment;
use App\Services\ListeningAssessmentGeneratorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ListeningAssessmentController extends Controller
{
    public function __construct(
        private readonly ListeningAssessmentGeneratorService $assessmentGenerator,
    ) {}

    public function index(Request $request): View
    {
        $query = ListeningAssessment::query()
            ->with(['user', 'mediaItem'])
            ->withCount(['questions', 'attempts'])
            ->latest();

        if ($search = $request->string('q')->trim()->toString()) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', '%'.$search.'%')
                    ->orWhereHas('mediaItem', fn ($mq) => $mq->where('title', 'like', '%'.$search.'%'));
            });
        }

        if ($type = $request->string('type')->trim()->toString()) {
            $query->where('type', $type);
        }

        if ($status = $request->string('status')->trim()->toString()) {
            $query->where('status', $status);
        }

        return view('admin.listening-assessments.index', [
            'assessments' => $query->paginate(25)->withQueryString(),
            'search' => $search ?? '',
            'type' => $type ?? '',
            'status' => $status ?? '',
        ]);
    }

    public function show(ListeningAssessment $listeningAssessment): View
    {
        $listeningAssessment->load([
            'user',
            'mediaItem',
            'questions' => fn ($q) => $q->orderBy('order'),
            'attempts' => fn ($q) => $q->with('user')->latest()->limit(20),
        ]);

        return view('admin.listening-assessments.show', [
            'assessment' => $listeningAssessment,
        ]);
    }

    public function destroy(ListeningAssessment $listeningAssessment): RedirectResponse
    {
        $mediaItemId = $listeningAssessment->media_item_id;
        $listeningAssessment->delete();

        return redirect()->route('admin.media-items.show', $mediaItemId)
            ->with('success', 'Đã xóa bài assessment.');
    }

    public function regenerate(ListeningAssessment $listeningAssessment): RedirectResponse
    {
        $mediaItem = $listeningAssessment->mediaItem;

        if (! $mediaItem || ! $mediaItem->isAnalysisReady()) {
            return back()->with('error', 'Media chưa sẵn sàng để tạo lại câu hỏi.');
        }

        $this->assessmentGenerator->generate($mediaItem, $listeningAssessment->type);

        return back()->with('success', 'Đã tạo lại bài '.$listeningAssessment->type.'.');
    }
}
