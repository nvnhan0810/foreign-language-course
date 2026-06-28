<?php

namespace Tests\Unit;

use App\Models\ListeningQuestion;
use App\Models\MediaItem;
use App\Models\User;
use App\Services\ListeningSessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListeningSessionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_session_options_reflect_bank_availability(): void
    {
        $user = User::factory()->create();
        $mediaItem = MediaItem::query()->create([
            'user_id' => $user->id,
            'title' => 'Sample talk',
            'url' => 'https://example.com/video',
            'type' => MediaItem::TYPE_YOUTUBE,
            'frequency' => 'weekly',
            'question_bank_status' => MediaItem::QUESTION_BANK_READY,
            'question_bank_count' => 12,
        ]);

        for ($i = 1; $i <= 12; $i++) {
            ListeningQuestion::query()->create([
                'media_item_id' => $mediaItem->id,
                'order' => $i,
                'question_type' => ListeningQuestion::TYPE_MCQ,
                'prompt' => "Question {$i}?",
                'options' => ['A', 'B', 'C', 'D'],
                'correct_answer' => 'A',
            ]);
        }

        $options = app(ListeningSessionService::class)->sessionOptions($mediaItem->fresh());

        $quiz = collect($options)->firstWhere('type', 'quiz');
        $exam = collect($options)->firstWhere('type', 'exam');

        $this->assertTrue($quiz['available']);
        $this->assertFalse($exam['available']);
    }

    public function test_start_session_randomizes_questions_from_bank(): void
    {
        $user = User::factory()->create();
        $mediaItem = MediaItem::query()->create([
            'user_id' => $user->id,
            'title' => 'Sample talk',
            'url' => 'https://example.com/video',
            'type' => MediaItem::TYPE_YOUTUBE,
            'frequency' => 'weekly',
            'question_bank_status' => MediaItem::QUESTION_BANK_READY,
            'question_bank_count' => 10,
        ]);

        for ($i = 1; $i <= 10; $i++) {
            ListeningQuestion::query()->create([
                'media_item_id' => $mediaItem->id,
                'order' => $i,
                'question_type' => ListeningQuestion::TYPE_MCQ,
                'prompt' => "Question {$i}?",
                'options' => ['A', 'B', 'C', 'D'],
                'correct_answer' => 'A',
            ]);
        }

        $session = app(ListeningSessionService::class)->startSession(
            $mediaItem->fresh(),
            $user,
            'quiz'
        );

        $this->assertCount(5, $session['questions']);
        $this->assertSame('quiz', $session['type']);
        $this->assertDatabaseHas('listening_assessments', [
            'id' => $session['assessment_id'],
            'media_item_id' => $mediaItem->id,
            'type' => 'quiz',
            'question_count' => 5,
        ]);
    }
}
