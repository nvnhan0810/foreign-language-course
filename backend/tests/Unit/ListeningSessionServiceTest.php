<?php

namespace Tests\Unit;

use App\Models\ListeningQuestion;
use App\Models\MediaItem;
use App\Models\User;
use Flc\Listening\Application\Command\InitializeSessionQuestions;
use Flc\Listening\Application\Command\ResumeOrStartListeningSession;
use Flc\Listening\Application\Command\StartListeningSession;
use Flc\Listening\Application\Query\GetListeningSessionOptions;
use Flc\Shared\Application\CommandBus;
use Flc\Shared\Application\QueryBus;
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

        $options = app(QueryBus::class)->ask(new GetListeningSessionOptions($mediaItem->id));

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

        $session = app(CommandBus::class)->dispatch(new StartListeningSession(
            $mediaItem->id,
            $user->id,
            'quiz',
        ));

        $this->assertCount(5, $session['questions']);
        $this->assertSame('quiz', $session['type']);
        $this->assertDatabaseHas('listening_assessments', [
            'id' => $session['assessment_id'],
            'media_item_id' => $mediaItem->id,
            'type' => 'quiz',
            'question_count' => 5,
        ]);
    }

    public function test_resume_or_start_session_reuses_unfinished_assessment(): void
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

        $commands = app(CommandBus::class);
        $first = $commands->dispatch(new StartListeningSession($mediaItem->id, $user->id, 'quiz'));
        $second = $commands->dispatch(new ResumeOrStartListeningSession($mediaItem->id, $user->id, 'quiz'));

        $this->assertSame($first['assessment_id'], $second['assessment_id']);
        $this->assertTrue($second['resumed'] ?? false);
    }

    public function test_initialize_session_questions_populates_question_ids(): void
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

        $commands = app(CommandBus::class);
        $session = $commands->dispatch(new StartListeningSession($mediaItem->id, $user->id, 'quiz'));

        $assessment = \App\Models\ListeningAssessment::query()->findOrFail($session['assessment_id']);
        $assessment->update(['question_ids' => null, 'question_count' => 0]);

        $questionIds = $commands->dispatch(new InitializeSessionQuestions($assessment->id, $user->id));
        $bankIds = $mediaItem->listeningQuestions()->pluck('id')->all();

        $this->assertCount(5, $questionIds);

        foreach ($questionIds as $id) {
            $this->assertContains($id, $bankIds);
        }
    }
}
