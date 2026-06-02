<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class YouTubeTranscriptService
{
    public function fetch(string $videoId, string $language = 'en'): ?string
    {
        $pageHtml = Http::timeout(15)
            ->withHeaders(['User-Agent' => 'Mozilla/5.0'])
            ->get("https://www.youtube.com/watch?v={$videoId}")
            ->body();

        if (! preg_match('/"captionTracks":(\[.*?\])/', $pageHtml, $matches)) {
            return null;
        }

        $tracks = json_decode($matches[1], true);

        if (! is_array($tracks) || $tracks === []) {
            return null;
        }

        $trackUrl = $this->selectTrackUrl($tracks, $language);

        if (! $trackUrl) {
            return null;
        }

        $captionXml = Http::timeout(15)->get($trackUrl)->body();

        return $this->parseCaptionXml($captionXml);
    }

    /**
     * @param  array<int, array<string, mixed>>  $tracks
     */
    private function selectTrackUrl(array $tracks, string $language): ?string
    {
        foreach ($tracks as $track) {
            if (($track['languageCode'] ?? '') === $language && ! empty($track['baseUrl'])) {
                return $track['baseUrl'];
            }
        }

        foreach ($tracks as $track) {
            if (! empty($track['baseUrl'])) {
                return $track['baseUrl'];
            }
        }

        return null;
    }

    private function parseCaptionXml(string $xml): ?string
    {
        if ($xml === '') {
            return null;
        }

        $document = new \DOMDocument;
        libxml_use_internal_errors(true);
        $document->loadXML($xml);
        libxml_clear_errors();

        $segments = [];

        foreach ($document->getElementsByTagName('text') as $node) {
            $text = html_entity_decode($node->textContent ?? '', ENT_QUOTES | ENT_HTML5);
            $text = trim(preg_replace('/\s+/', ' ', $text) ?? '');

            if ($text !== '') {
                $segments[] = $text;
            }
        }

        if ($segments === []) {
            return null;
        }

        return implode(' ', $segments);
    }
}
