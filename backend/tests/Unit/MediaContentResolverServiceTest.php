<?php

namespace Tests\Unit;

use App\Exceptions\TranscriptUnavailableException;
use App\Models\MediaItem;
use App\Models\User;
use App\Services\MediaContentResolverService;
use App\Services\YouTubeTranscriptService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class MediaContentResolverServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_youtube_without_transcript_throws_and_does_not_use_metadata(): void
    {
        $user = User::factory()->create();
        $mediaItem = MediaItem::query()->create([
            'user_id' => $user->id,
            'title' => 'Sample video',
            'url' => 'https://www.youtube.com/watch?v=abc123',
            'type' => MediaItem::TYPE_YOUTUBE,
            'source_id' => 'abc123',
            'language' => 'en',
            'frequency' => 'weekly',
        ]);

        $youtube = Mockery::mock(YouTubeTranscriptService::class);
        $youtube->shouldReceive('fetch')
            ->once()
            ->with('abc123', 'en')
            ->andReturn(null);

        $service = new MediaContentResolverService($youtube);

        try {
            $service->resolve($mediaItem);
            $this->fail('Expected TranscriptUnavailableException');
        } catch (TranscriptUnavailableException $e) {
            $this->assertSame($mediaItem->id, $e->mediaItemId);
        }
    }

    public function test_append_transcript_unavailable_note_adds_system_note(): void
    {
        $user = User::factory()->create();
        $mediaItem = MediaItem::query()->create([
            'user_id' => $user->id,
            'title' => 'Sample video',
            'url' => 'https://www.youtube.com/watch?v=abc123',
            'type' => MediaItem::TYPE_YOUTUBE,
            'source_id' => 'abc123',
            'language' => 'en',
            'frequency' => 'weekly',
            'notes' => 'Ghi chú của admin',
        ]);

        $service = new MediaContentResolverService(
            Mockery::mock(YouTubeTranscriptService::class)
        );

        $service->appendTranscriptUnavailableNote($mediaItem->fresh());

        $mediaItem->refresh();

        $this->assertStringContainsString('Ghi chú của admin', $mediaItem->notes);
        $this->assertStringContainsString('Không lấy được phụ đề/transcript', $mediaItem->notes);
    }

    public function test_append_transcript_unavailable_note_is_idempotent(): void
    {
        $user = User::factory()->create();
        $mediaItem = MediaItem::query()->create([
            'user_id' => $user->id,
            'title' => 'Sample video',
            'url' => 'https://www.youtube.com/watch?v=abc123',
            'type' => MediaItem::TYPE_YOUTUBE,
            'source_id' => 'abc123',
            'language' => 'en',
            'frequency' => 'weekly',
            'notes' => MediaContentResolverService::TRANSCRIPT_UNAVAILABLE_NOTE,
        ]);

        $service = new MediaContentResolverService(
            Mockery::mock(YouTubeTranscriptService::class)
        );

        $service->appendTranscriptUnavailableNote($mediaItem->fresh());

        $this->assertSame(
            MediaContentResolverService::TRANSCRIPT_UNAVAILABLE_NOTE,
            $mediaItem->fresh()->notes
        );
    }
}
