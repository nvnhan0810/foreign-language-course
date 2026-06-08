<?php

namespace App\Console\Commands;

use App\Services\TelegramAlertService;
use Illuminate\Console\Command;

class TelegramTestAlertCommand extends Command
{
    protected $signature = 'flc:telegram-test';

    protected $description = 'Gửi thử một cảnh báo Telegram (kiểm tra bot token & chat id)';

    public function handle(TelegramAlertService $telegram): int
    {
        if (! $telegram->isConfigured()) {
            $this->error('Telegram chưa cấu hình. Đặt TELEGRAM_BOT_TOKEN và TELEGRAM_CHAT_ID trong .env.');

            return self::FAILURE;
        }

        $ok = $telegram->send(
            '✅ <b>TEST</b> — '.config('app.name').' ('.config('app.env').")\n"
            .'<code>Telegram log alerts hoạt động.</code>'
        );

        if (! $ok) {
            $this->error('Gửi Telegram thất bại. Kiểm tra token, chat_id và bot đã được /start chưa.');

            return self::FAILURE;
        }

        $this->info('Đã gửi tin nhắn test lên Telegram.');

        return self::SUCCESS;
    }
}
