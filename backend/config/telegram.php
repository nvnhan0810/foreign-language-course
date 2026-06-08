<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Telegram log alerts
    |--------------------------------------------------------------------------
    |
    | Gửi log warning/error lên Telegram qua Bot API.
    | Tạo bot: @BotFather → /newbot. Chat ID: nhắn bot rồi gọi getUpdates, hoặc @userinfobot.
    |
    */

    'enabled' => env('TELEGRAM_ALERTS_ENABLED', true),

    'bot_token' => env('TELEGRAM_BOT_TOKEN'),

    'chat_id' => env('TELEGRAM_CHAT_ID'),

    /** Throttle trùng message (giây) — tránh spam khi lỗi lặp. */
    'dedupe_seconds' => (int) env('TELEGRAM_DEDUPE_SECONDS', 60),

];
