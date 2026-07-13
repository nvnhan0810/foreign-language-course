<?php

namespace Tests\Unit;

use App\Models\MediaItem as EloquentMediaItem;
use App\Models\User;
use Flc\Media\Application\Exception\TranscriptUnavailableException;
use Flc\Media\Infrastructure\Content\DefaultMediaContentResolver;
use Flc\Media\Infrastructure\External\YouTubeTranscriptService;
use Flc\Media\Infrastructure\Persistence\EloquentMediaItemRepository;
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
        $model = EloquentMediaItem::query()->create([
            'user_id' => $user->id,
            'title' => 'Sample video',
            'url' => 'https://www.youtube.com/watch?v=abc123',
            'type' => EloquentMediaItem::TYPE_YOUTUBE,
            'source_id' => 'abc123',
            'language' => 'en',
            'frequency' => 'weekly',
        ]);

        $youtube = Mockery::mock(YouTubeTranscriptService::class);
        $youtube->shouldReceive('fetch')
            ->once()
            ->with('abc123', 'en')
            ->andReturn(null);

        $resolver = new DefaultMediaContentResolver($youtube);
        $mediaItem = EloquentMediaItemRepository::toDomain($model);

        try {
            $resolver->resolve($mediaItem);
            $this->fail('Expected TranscriptUnavailableException');
        } catch (TranscriptUnavailableException $e) {
            $this->assertSame($mediaItem->id, $e->mediaItemId);
        }
    }

    public function test_append_transcript_unavailable_note_adds_system_note(): void
    {
        $user = User::factory()->create();
        $model = EloquentMediaItem::query()->create([
            'user_id' => $user->id,
            'title' => 'Sample video',
            'url' => 'https://www.youtube.com/watch?v=abc123',
            'type' => EloquentMediaItem::TYPE_YOUTUBE,
            'source_id' => 'abc123',
            'language' => 'en',
            'frequency' => 'weekly',
            'notes' => 'Ghi chú của admin',
        ]);

        $repo = new EloquentMediaItemRepository;
        $repo->appendTranscriptUnavailableNote($model->id);

        $model->refresh();

        $this->assertStringContainsString('Ghi chú của admin', $model->notes);
        $this->assertStringContainsString('Không lấy được phụ đề/transcript', $model->notes);
    }

    public function test_append_transcript_unavailable_note_is_idempotent(): void
    {
        $user = User::factory()->create();
        $note = 'Không lấy được phụ đề/transcript cho video YouTube. Bật caption trên YouTube hoặc dán transcript thủ công, rồi phân tích lại.';
        $model = EloquentMediaItem::query()->create([
            'user_id' => $user->id,
            'title' => 'Sample video',
            'url' => 'https://www.youtube.com/watch?v=abc123',
            'type' => EloquentMediaItem::TYPE_YOUTUBE,
            'source_id' => 'abc123',
            'language' => 'en',
            'frequency' => 'weekly',
            'notes' => $note,
        ]);

        $repo = new EloquentMediaItemRepository;
        $repo->appendTranscriptUnavailableNote($model->id);

        $this->assertSame($note, $model->fresh()->notes);
    }
}
