<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Monolog\Level;
use Monolog\LogRecord;

class TelegramAlertService
{
    public function isConfigured(): bool
    {
        if (! config('telegram.enabled', true)) {
            return false;
        }

        $token = config('telegram.bot_token');
        $chatId = config('telegram.chat_id');

        return is_string($token) && $token !== ''
            && is_string($chatId) && $chatId !== '';
    }

    public function sendLogAlert(LogRecord $record): bool
    {
        if (! $this->isConfigured()) {
            return false;
        }

        $dedupeKey = 'telegram_log:'.md5($record->level->getName().'|'.$record->message);
        $dedupeSeconds = max(0, (int) config('telegram.dedupe_seconds', 60));

        if ($dedupeSeconds > 0 && Cache::has($dedupeKey)) {
            return false;
        }

        $sent = $this->send($this->formatLogRecord($record));

        if ($sent && $dedupeSeconds > 0) {
            Cache::put($dedupeKey, true, $dedupeSeconds);
        }

        return $sent;
    }

    public function send(string $text): bool
    {
        if (! $this->isConfigured()) {
            return false;
        }

        $token = config('telegram.bot_token');
        $chatId = config('telegram.chat_id');

        $response = Http::timeout(5)
            ->asJson()
            ->post("https://api.telegram.org/bot{$token}/sendMessage", [
                'chat_id' => $chatId,
                'text' => mb_substr($text, 0, 4000),
                'parse_mode' => 'HTML',
                'disable_web_page_preview' => true,
            ]);

        return $response->successful();
    }

    private function formatLogRecord(LogRecord $record): string
    {
        $emoji = match ($record->level) {
            Level::Warning => '⚠️',
            Level::Error => '🔴',
            Level::Critical, Level::Alert, Level::Emergency => '🚨',
            default => 'ℹ️',
        };

        $level = strtoupper($record->level->getName());
        $app = config('app.name', 'Laravel');
        $env = config('app.env', 'production');
        $message = $this->escapeHtml($record->message);

        $lines = [
            "{$emoji} <b>{$level}</b> — {$this->escapeHtml($app)} ({$this->escapeHtml($env)})",
            "<code>{$message}</code>",
        ];

        if ($record->context !== []) {
            $context = json_encode(
                $record->context,
                JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR
            ) ?: '{}';
            if (strlen($context) > 800) {
                $context = substr($context, 0, 797).'...';
            }
            $lines[] = '<pre>'.$this->escapeHtml($context).'</pre>';
        }

        return implode("\n", $lines);
    }

    private function escapeHtml(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
