<?php

namespace App\Services;

use App\Models\MediaItem;

class MediaContentResolverService
{
    public const SOURCE_TRANSCRIPT = 'transcript';

    public const SOURCE_METADATA = 'metadata';

    public const SOURCE_NOTES = 'notes';

    public const SOURCE_TITLE = 'title_only';

    public function __construct(
        private readonly YouTubeTranscriptService $youtubeTranscript,
        private readonly YouTubeMetadataService $youtubeMetadata,
    ) {}

    /**
     * @return array{content: string, source: string, metadata?: array<string, mixed>}
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

            $metadata = $this->youtubeMetadata->fetch($mediaItem->source_id);

            if ($metadata) {
                $content = $this->youtubeMetadata->toContentText($metadata, $mediaItem->title);

                if (mb_strlen(trim($content)) >= 20) {
                    return [
                        'content' => $content,
                        'source' => self::SOURCE_METADATA,
                        'metadata' => $metadata,
                    ];
                }
            }
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
}
