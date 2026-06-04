<?php

namespace App\Console\Commands;

use App\Services\VocabQuizReminderService;
use Illuminate\Console\Command;

class SendVocabQuizRemindersCommand extends Command
{
    protected $signature = 'flc:vocab-quiz-reminders {slot : midday|evening}';

    protected $description = 'Send FCM vocab quiz reminders (Asia/Ho_Chi_Minh schedule)';

    public function handle(VocabQuizReminderService $reminders): int
    {
        $slot = $this->argument('slot');

        if (! in_array($slot, [VocabQuizReminderService::SLOT_MIDDAY, VocabQuizReminderService::SLOT_EVENING], true)) {
            $this->error('Invalid slot. Use midday or evening.');

            return self::FAILURE;
        }

        $stats = $reminders->sendReminders($slot);

        $this->info(sprintf(
            '[%s] sent=%d skipped=%d failed=%d',
            $slot,
            $stats['sent'],
            $stats['skipped'],
            $stats['failed'],
        ));

        return self::SUCCESS;
    }
}
