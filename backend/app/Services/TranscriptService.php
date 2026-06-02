<?php

namespace App\Services;

use App\Models\MediaItem;

class TranscriptService
{
    public function __construct(
        private readonly YouTubeTranscriptService $youtubeTranscript,
    ) {}

    public function resolve(MediaItem $mediaItem): string
    {
        if ($mediaItem->transcript) {
            return $mediaItem->transcript;
        }

        if ($mediaItem->type === MediaItem::TYPE_YOUTUBE && $mediaItem->source_id) {
            $transcript = $this->youtubeTranscript->fetch(
                $mediaItem->source_id,
                $mediaItem->language
            );

            if ($transcript) {
                return $transcript;
            }
        }

        throw new \RuntimeException(
            'Could not obtain transcript. For YouTube, ensure captions are available. For MP3 uploads, include a transcript when saving the media item.'
        );
    }
}
