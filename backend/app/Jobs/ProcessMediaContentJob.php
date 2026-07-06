<?php

namespace App\Jobs;

use App\Exceptions\TranscriptUnavailableException;
use App\Models\MediaItem;
use App\Services\ContentAnalysisService;
use App\Services\ListeningAssessmentGeneratorService;
use App\Services\MediaContentResolverService;
use App\Services\MediaKeyVocabularyImporter;
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
        MediaContentResolverService $contentResolver,
        ContentAnalysisService $analysisService,
        MediaKeyVocabularyImporter $vocabularyImporter,
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
            $resolved = $contentResolver->resolve($mediaItem);
            $content = $resolved['content'];
            $contentSource = $resolved['source'];

            $analysis = $analysisService->analyze(
                $content,
                $mediaItem->title,
                $mediaItem->language,
                $contentSource
            );

            $analysis['source_content'] = $content;
            $analysis['content_source'] = $contentSource;

            $analysis['vocabulary_import'] = $vocabularyImporter->importFromAnalysis($mediaItem, $analysis);

            $mediaItem->update([
                'transcript' => $contentSource === MediaContentResolverService::SOURCE_TRANSCRIPT ? $content : $mediaItem->transcript,
                'analysis_payload' => $analysis,
                'difficulty' => MediaItem::normalizeDifficulty($analysis['difficulty'] ?? null),
                'analysis_status' => MediaItem::ANALYSIS_READY,
                'analyzed_at' => now(),
                'analysis_error' => null,
            ]);

            $assessmentGenerator->generateQuestionBank($mediaItem->fresh());
        } catch (TranscriptUnavailableException $e) {
            $contentResolver->appendTranscriptUnavailableNote($mediaItem->fresh());

            $mediaItem->update([
                'analysis_status' => MediaItem::ANALYSIS_FAILED,
                'analysis_error' => $e->getMessage(),
            ]);
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
