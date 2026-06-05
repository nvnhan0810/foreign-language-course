<?php

namespace App\Services;

use App\Models\DevicePushToken;
use App\Models\QuizAttempt;
use App\Models\User;
use App\Models\UserNotificationPreference;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class VocabQuizReminderService
{
    public const SLOT_MIDDAY = 'midday';

    public const SLOT_EVENING = 'evening';

    public function __construct(
        private readonly FcmService $fcm,
        private readonly AppSettingService $settings,
    ) {}

    /**
     * @return array{sent: int, skipped: int, failed: int}
     */
    public function sendReminders(string $slot): array
    {
        if (! $this->settings->getBool('vocab_quiz_push_enabled', true)) {
            return ['sent' => 0, 'skipped' => 0, 'failed' => 0];
        }

        if (! $this->fcm->isConfigured()) {
            return ['sent' => 0, 'skipped' => 0, 'failed' => 0];
        }

        $stats = ['sent' => 0, 'skipped' => 0, 'failed' => 0];

        $this->eligibleUsers()->each(function (User $user) use ($slot, &$stats) {
            $attemptsToday = $this->quizAttemptsTodayCount($user->id);

            if (! $this->shouldNotify($slot, $attemptsToday)) {
                $stats['skipped']++;

                return;
            }

            [$title, $body] = $this->messageForSlot($slot);

            $sentAny = false;
            foreach ($user->pushTokens as $device) {
                if ($this->fcm->sendToToken($device->token, $title, $body, [
                    'type' => 'vocab_quiz',
                    'route' => '/home/quiz',
                    'autostart' => '1',
                ])) {
                    $sentAny = true;
                } else {
                    $stats['failed']++;
                }
            }

            if ($sentAny) {
                $stats['sent']++;
            }
        });

        return $stats;
    }

    /**
     * @return Collection<int, User>
     */
    private function eligibleUsers(): Collection
    {
        return User::query()
            ->has('vocabularies', '>=', 4)
            ->whereHas('pushTokens')
            ->with(['pushTokens', 'notificationPreference'])
            ->get()
            ->filter(function (User $user) {
                if ($user->pushTokens->isEmpty()) {
                    return false;
                }

                $pref = $user->notificationPreference;

                return $pref === null || $pref->vocab_quiz_push_enabled;
            });
    }

    private function shouldNotify(string $slot, int $attemptsToday): bool
    {
        return match ($slot) {
            self::SLOT_MIDDAY => $attemptsToday === 0,
            self::SLOT_EVENING => $attemptsToday <= 1,
            default => false,
        };
    }

    private function quizAttemptsTodayCount(int $userId): int
    {
        $tz = 'Asia/Ho_Chi_Minh';
        $start = Carbon::now($tz)->startOfDay();
        $end = Carbon::now($tz)->endOfDay();

        return QuizAttempt::query()
            ->where('user_id', $userId)
            ->whereBetween('created_at', [$start, $end])
            ->count();
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function messageForSlot(string $slot): array
    {
        $appName = $this->settings->get('app_name', 'Foreign Learner');

        return match ($slot) {
            self::SLOT_MIDDAY => [
                $appName,
                'Buổi sáng bạn chưa ôn từ nào. Chạm để làm quiz từ vựng.',
            ],
            self::SLOT_EVENING => [
                $appName,
                'Hôm nay bạn mới ôn ít từ. Chạm để làm thêm quiz tối này.',
            ],
            default => [$appName, 'Ôn từ vựng ngay nhé.'],
        };
    }

    public static function ensurePreference(User $user): UserNotificationPreference
    {
        return UserNotificationPreference::query()->firstOrCreate(
            ['user_id' => $user->id],
            ['vocab_quiz_push_enabled' => true]
        );
    }
}
