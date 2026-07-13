<?php

namespace Tests\Unit;

use Flc\Media\Infrastructure\External\YouTubeTranscriptService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class YouTubeTranscriptServiceTest extends TestCase
{
    public function test_fetches_transcript_via_android_player_api(): void
    {
        Http::fake([
            'www.youtube.com/youtubei/v1/player' => Http::response([
                'captions' => [
                    'playerCaptionsTracklistRenderer' => [
                        'captionTracks' => [
                            [
                                'languageCode' => 'en',
                                'baseUrl' => 'https://www.youtube.com/api/timedtext?v=abc123&lang=en',
                            ],
                        ],
                    ],
                ],
            ]),
            'www.youtube.com/api/timedtext*' => Http::response(
                '<?xml version="1.0"?><transcript><text start="0" dur="2">Hello world</text></transcript>'
            ),
        ]);

        $transcript = app(YouTubeTranscriptService::class)->fetch('abc123', 'en');

        $this->assertSame('Hello world', $transcript);
    }

    public function test_prefers_requested_language_track(): void
    {
        Http::fake([
            'www.youtube.com/youtubei/v1/player' => Http::response([
                'captions' => [
                    'playerCaptionsTracklistRenderer' => [
                        'captionTracks' => [
                            [
                                'languageCode' => 'vi',
                                'baseUrl' => 'https://www.youtube.com/api/timedtext?v=abc123&lang=vi',
                            ],
                            [
                                'languageCode' => 'en',
                                'baseUrl' => 'https://www.youtube.com/api/timedtext?v=abc123&lang=en',
                            ],
                        ],
                    ],
                ],
            ]),
            'www.youtube.com/api/timedtext*' => function ($request) {
                $lang = str_contains($request->url(), 'lang=en') ? 'en' : 'vi';

                return Http::response(
                    '<?xml version="1.0"?><transcript><text start="0" dur="2">'.$lang.'</text></transcript>'
                );
            },
        ]);

        $transcript = app(YouTubeTranscriptService::class)->fetch('abc123', 'en');

        $this->assertSame('en', $transcript);
    }

    public function test_returns_null_when_no_caption_tracks(): void
    {
        Http::fake([
            'www.youtube.com/youtubei/v1/player' => Http::response([]),
            'www.youtube.com/watch*' => Http::response('<html>no captions here</html>'),
        ]);

        $transcript = app(YouTubeTranscriptService::class)->fetch('abc123', 'en');

        $this->assertNull($transcript);
    }
}
