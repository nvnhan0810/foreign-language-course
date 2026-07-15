<?php

namespace Tests\Feature;

use App\Jobs\ProcessMediaContentJob;
use App\Models\MediaItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ListeningApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_save_youtube_url_and_dispatch_analysis(): void
    {
        Bus::fake();

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/listening/media', [
            'title' => 'English Listening Lesson',
            'type' => 'youtube',
            'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'language' => 'en',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.type', 'youtube')
            ->assertJsonPath('data.source_id', 'dQw4w9WgXcQ')
            ->assertJsonPath('data.analysis_status', 'pending');

        Bus::assertDispatched(ProcessMediaContentJob::class);

        $this->assertDatabaseHas('media_items', [
            'user_id' => $user->id,
            'title' => 'English Listening Lesson',
            'type' => 'youtube',
            'source_id' => 'dQw4w9WgXcQ',
        ]);
    }

    public function test_can_upload_mp3_and_dispatch_analysis(): void
    {
        Bus::fake();
        Storage::fake('local');

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $file = UploadedFile::fake()->create('lesson.mp3', 100, 'audio/mpeg');

        $response = $this->post('/api/listening/media', [
            'title' => 'Podcast Episode',
            'type' => 'mp3',
            'audio' => $file,
            'language' => 'en',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.type', 'mp3')
            ->assertJsonPath('data.analysis_status', 'pending');

        Bus::assertDispatched(ProcessMediaContentJob::class);

        $item = MediaItem::query()->first();
        $this->assertNotNull($item->audio_path);
        Storage::disk('local')->assertExists($item->audio_path);
    }

    public function test_rejects_invalid_youtube_url(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/listening/media', [
            'title' => 'Bad URL',
            'type' => 'youtube',
            'url' => 'https://example.com/not-youtube',
        ]);

        $response->assertStatus(422);
    }

    public function test_can_preview_youtube_url(): void
    {
        \Illuminate\Support\Facades\Http::fake([
            'www.youtube.com/oembed*' => \Illuminate\Support\Facades\Http::response([
                'title' => 'Sample Lesson - YouTube',
                'author_name' => 'FLC Channel',
            ]),
        ]);

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/listening/media/youtube-preview', [
            'url' => 'https://youtu.be/dQw4w9WgXcQ',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.video_id', 'dQw4w9WgXcQ')
            ->assertJsonPath('data.title', 'Sample Lesson')
            ->assertJsonPath('data.author_name', 'FLC Channel')
            ->assertJsonPath('data.url', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ');
    }

    public function test_preview_rejects_invalid_youtube_url(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/listening/media/youtube-preview', [
            'url' => 'https://example.com/not-youtube',
        ])->assertStatus(422);
    }

}
