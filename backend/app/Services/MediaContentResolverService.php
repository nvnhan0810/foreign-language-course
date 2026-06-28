<?php

namespace App\Services;

use App\Exceptions\TranscriptUnavailableException;
use App\Models\MediaItem;
use Illuminate\Support\Facades\Log;

class MediaContentResolverService
{
    public const SOURCE_TRANSCRIPT = 'transcript';

    public const SOURCE_METADATA = 'metadata';

    public const SOURCE_NOTES = 'notes';

    public const SOURCE_TITLE = 'title_only';

    public const TRANSCRIPT_UNAVAILABLE_NOTE = '[Hệ thống] Không lấy được phụ đề/transcript cho video YouTube. Bật caption trên YouTube hoặc dán transcript thủ công, rồi phân tích lại.';

    public function __construct(
        private readonly YouTubeTranscriptService $youtubeTranscript,
    ) {}

    /**
     * @return array{content: string, source: string}
     */
    public function resolve(MediaItem $mediaItem): array
    {
        if ($mediaItem->transcript) {
            return [
                'content' => $mediaItem->transcript,
                'source' => self::SOURCE_TRANSCRIPT,
            ];
        }

        if ($mediaItem->type === MediaItem::TYPE_YOUTUBE && $mediaItem->source_id) {
            $transcript = $this->youtubeTranscript->fetch(
                $mediaItem->source_id,
                $mediaItem->language
            );

            if ($transcript) {
                return [
                    'content' => $transcript,
                    'source' => self::SOURCE_TRANSCRIPT,
                ];
            }

            Log::warning('YouTube transcript unavailable, skipping analysis', [
                'media_item_id' => $mediaItem->id,
                'video_id' => $mediaItem->source_id,
                'language' => $mediaItem->language,
            ]);

            throw new TranscriptUnavailableException(
                $mediaItem->id,
                $mediaItem->source_id,
            );
        }

        if ($mediaItem->notes && trim($mediaItem->notes) !== '') {
            return [
                'content' => "Title: {$mediaItem->title}\n\nNotes: {$mediaItem->notes}\n\nURL: {$mediaItem->url}",
                'source' => self::SOURCE_NOTES,
            ];
        }

        if (trim($mediaItem->title) !== '') {
            return [
                'content' => "Title: {$mediaItem->title}\n\nURL: {$mediaItem->url}",
                'source' => self::SOURCE_TITLE,
            ];
        }

        throw new \RuntimeException(
            'Could not obtain content for this media item. Add notes/transcript manually or try again later.'
        );
    }

    public function appendTranscriptUnavailableNote(MediaItem $mediaItem): void
    {
        $existing = trim($mediaItem->notes ?? '');

        if (str_contains($existing, 'Không lấy được phụ đề/transcript')) {
            return;
        }

        $mediaItem->update([
            'notes' => $existing === ''
                ? self::TRANSCRIPT_UNAVAILABLE_NOTE
                : $existing."\n\n".self::TRANSCRIPT_UNAVAILABLE_NOTE,
        ]);
    }
}
