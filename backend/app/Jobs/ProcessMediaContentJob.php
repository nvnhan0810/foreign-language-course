<?php

namespace App\Jobs;

use App\Models\MediaItem;
use App\Services\ContentAnalysisService;
use App\Services\ListeningAssessmentGeneratorService;
use App\Services\TranscriptService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ProcessMediaContentJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 900;

    public function __construct(public int $mediaItemId) {}

    public function handle(
        TranscriptService $transcriptService,
        ContentAnalysisService $analysisService,
        ListeningAssessmentGeneratorService $assessmentGenerator,
    ): void {
        $mediaItem = MediaItem::query()->find($this->mediaItemId);

        if (! $mediaItem) {
            return;
        }

        $mediaItem->update([
            'analysis_status' => MediaItem::ANALYSIS_PROCESSING,
            'analysis_error' => null,
        ]);

        try {
            $transcript = $transcriptService->resolve($mediaItem);

            $analysis = $analysisService->analyze(
                $transcript,
                $mediaItem->title,
                $mediaItem->language
            );

            $mediaItem->update([
                'transcript' => $transcript,
                'analysis_payload' => $analysis,
                'analysis_status' => MediaItem::ANALYSIS_READY,
                'analyzed_at' => now(),
                'analysis_error' => null,
            ]);

            $assessmentGenerator->generateAll($mediaItem->fresh());
        } catch (\Throwable $e) {
            Log::error('ProcessMediaContentJob failed', [
                'media_item_id' => $this->mediaItemId,
                'error' => $e->getMessage(),
            ]);

            $mediaItem->update([
                'analysis_status' => MediaItem::ANALYSIS_FAILED,
                'analysis_error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
