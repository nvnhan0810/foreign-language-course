<?php

namespace Tests\Feature;

use App\Models\DevicePushToken;
use App\Models\QuizAttempt;
use App\Models\User;
use App\Models\Vocabulary;
use App\Services\AppSettingService;
use App\Services\FcmService;
use App\Services\VocabQuizReminderService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class VocabQuizReminderTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_midday_sends_when_no_attempts_today(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-04 11:00:00', 'Asia/Ho_Chi_Minh'));

        $user = $this->userWithToken();
        $this->seedVocabularies($user, 4);

        $fcm = Mockery::mock(FcmService::class);
        $fcm->shouldReceive('isConfigured')->andReturn(true);
        $fcm->shouldReceive('sendToToken')->once()->andReturn(true);
        $this->app->instance(FcmService::class, $fcm);

        $stats = app(VocabQuizReminderService::class)->sendReminders(VocabQuizReminderService::SLOT_MIDDAY);

        $this->assertSame(1, $stats['sent']);
    }

    public function test_midday_skips_when_already_quizzed_today(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-04 11:00:00', 'Asia/Ho_Chi_Minh'));

        $user = $this->userWithToken();
        $vocabs = $this->seedVocabularies($user, 4);

        QuizAttempt::query()->create([
            'vocabulary_id' => $vocabs[0]->id,
            'user_id' => $user->id,
            'correct' => true,
            'question_type' => 'word_to_definition',
        ]);

        $fcm = Mockery::mock(FcmService::class);
        $fcm->shouldReceive('isConfigured')->andReturn(true);
        $fcm->shouldReceive('sendToToken')->never();
        $this->app->instance(FcmService::class, $fcm);

        $stats = app(VocabQuizReminderService::class)->sendReminders(VocabQuizReminderService::SLOT_MIDDAY);

        $this->assertSame(0, $stats['sent']);
        $this->assertSame(1, $stats['skipped']);
    }

    public function test_evening_sends_when_at_most_one_attempt_today(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-04 20:00:00', 'Asia/Ho_Chi_Minh'));

        $user = $this->userWithToken();
        $vocabs = $this->seedVocabularies($user, 4);

        QuizAttempt::query()->create([
            'vocabulary_id' => $vocabs[0]->id,
            'user_id' => $user->id,
            'correct' => false,
            'question_type' => 'definition_to_word',
        ]);

        $fcm = Mockery::mock(FcmService::class);
        $fcm->shouldReceive('isConfigured')->andReturn(true);
        $fcm->shouldReceive('sendToToken')->once()->andReturn(true);
        $this->app->instance(FcmService::class, $fcm);

        $stats = app(VocabQuizReminderService::class)->sendReminders(VocabQuizReminderService::SLOT_EVENING);

        $this->assertSame(1, $stats['sent']);
    }

    public function test_evening_skips_when_two_attempts_today(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-04 20:00:00', 'Asia/Ho_Chi_Minh'));

        $user = $this->userWithToken();
        $vocabs = $this->seedVocabularies($user, 4);

        foreach ($vocabs->take(2) as $vocab) {
            QuizAttempt::query()->create([
                'vocabulary_id' => $vocab->id,
                'user_id' => $user->id,
                'correct' => true,
                'question_type' => 'word_to_definition',
            ]);
        }

        $fcm = Mockery::mock(FcmService::class);
        $fcm->shouldReceive('isConfigured')->andReturn(true);
        $fcm->shouldReceive('sendToToken')->never();
        $this->app->instance(FcmService::class, $fcm);

        $stats = app(VocabQuizReminderService::class)->sendReminders(VocabQuizReminderService::SLOT_EVENING);

        $this->assertSame(0, $stats['sent']);
        $this->assertSame(1, $stats['skipped']);
    }

    public function test_push_token_api_stores_token(): void
    {
        $user = User::factory()->create();
        \Laravel\Sanctum\Sanctum::actingAs($user);

        $this->postJson('/api/me/push-token', [
            'token' => 'fcm-device-token-abc',
            'platform' => 'ios',
        ])->assertOk();

        $this->assertDatabaseHas('device_push_tokens', [
            'user_id' => $user->id,
            'token' => 'fcm-device-token-abc',
            'platform' => 'ios',
        ]);
    }

    private function userWithToken(): User
    {
        $user = User::factory()->create();

        DevicePushToken::query()->create([
            'user_id' => $user->id,
            'token' => 'test-fcm-token',
            'platform' => 'android',
        ]);

        app(AppSettingService::class)->set('vocab_quiz_push_enabled', true);

        return $user;
    }

    /**
     * @return \Illuminate\Support\Collection<int, Vocabulary>
     */
    private function seedVocabularies(User $user, int $count)
    {
        $items = collect();

        for ($i = 0; $i < $count; $i++) {
            $items->push(Vocabulary::query()->create([
                'user_id' => $user->id,
                'word' => "word{$i}",
                'meanings' => [['definition' => "definition {$i}"]],
            ]));
        }

        return $items;
    }
}
