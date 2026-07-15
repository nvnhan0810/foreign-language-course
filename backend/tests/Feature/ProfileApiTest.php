<?php

namespace Tests\Feature;

use App\Models\DictionaryEntry;
use App\Models\DictionaryMeaning;
use App\Models\ListeningAssessment;
use App\Models\ListeningAttempt;
use App\Models\MediaItem;
use App\Models\QuizAttempt;
use App\Models\User;
use App\Models\Vocabulary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProfileApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_returns_user_stats_and_history(): void
    {
        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);
        Sanctum::actingAs($user);

        $entry = DictionaryEntry::query()->create([
            'word' => 'hello',
            'source' => 'user_save',
            'is_curated' => false,
            'save_count' => 1,
        ]);
        DictionaryMeaning::query()->create([
            'dictionary_entry_id' => $entry->id,
            'definition' => 'xin chào',
            'position' => 0,
        ]);
        $vocabulary = Vocabulary::query()->create([
            'user_id' => $user->id,
            'dictionary_entry_id' => $entry->id,
        ]);

        QuizAttempt::query()->create([
            'vocabulary_id' => $vocabulary->id,
            'user_id' => $user->id,
            'correct' => true,
            'question_type' => 'definition',
        ]);
        QuizAttempt::query()->create([
            'vocabulary_id' => $vocabulary->id,
            'user_id' => $user->id,
            'correct' => false,
            'question_type' => 'definition',
        ]);

        $media = MediaItem::query()->create([
            'user_id' => $user->id,
            'title' => 'BBC News',
            'url' => 'https://example.com/audio',
            'type' => 'youtube',
            'frequency' => 'weekly',
        ]);

        $assessment = ListeningAssessment::query()->create([
            'media_item_id' => $media->id,
            'user_id' => $user->id,
            'type' => ListeningAssessment::TYPE_TEST,
            'title' => 'Listening Test - BBC',
            'question_count' => 10,
            'status' => ListeningAssessment::STATUS_READY,
        ]);

        ListeningAttempt::query()->create([
            'listening_assessment_id' => $assessment->id,
            'user_id' => $user->id,
            'score' => 8,
            'total' => 10,
            'percentage' => 80,
            'answers' => [],
            'completed_at' => now(),
        ]);

        $response = $this->getJson('/api/profile');

        $response->assertOk()
            ->assertJsonPath('user.name', 'Test User')
            ->assertJsonPath('user.email', 'test@example.com')
            ->assertJsonPath('stats.vocabulary_count', 1)
            ->assertJsonPath('stats.media_count', 1)
            ->assertJsonPath('stats.average_score_percent', 65)
            ->assertJsonCount(2, 'history');

        $history = $response->json('history');
        $kinds = collect($history)->pluck('kind')->all();
        $this->assertContains('listening', $kinds);
        $this->assertContains('vocab', $kinds);
    }

    public function test_profile_requires_authentication(): void
    {
        $this->getJson('/api/profile')->assertUnauthorized();
    }
}
