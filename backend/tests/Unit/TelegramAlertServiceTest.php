<?php

namespace Tests\Unit;

use App\Services\TelegramAlertService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Monolog\Level;
use Monolog\LogRecord;
use Tests\TestCase;

class TelegramAlertServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'telegram.enabled' => true,
            'telegram.bot_token' => 'test-token',
            'telegram.chat_id' => '999',
            'telegram.dedupe_seconds' => 0,
            'app.name' => 'FLC Test',
            'app.env' => 'testing',
        ]);
    }

    public function test_sends_warning_to_telegram_api(): void
    {
        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true], 200),
        ]);

        $record = new LogRecord(
            datetime: new \DateTimeImmutable,
            channel: 'test',
            level: Level::Warning,
            message: 'Something went wrong',
            context: ['user_id' => 1],
            extra: [],
        );

        $sent = app(TelegramAlertService::class)->sendLogAlert($record);

        $this->assertTrue($sent);
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'bottest-token/sendMessage')
                && $request['chat_id'] === '999'
                && str_contains($request['text'], 'WARNING')
                && str_contains($request['text'], 'Something went wrong');
        });
    }

    public function test_skips_when_not_configured(): void
    {
        config(['telegram.bot_token' => '']);

        Http::fake();

        $record = new LogRecord(
            datetime: new \DateTimeImmutable,
            channel: 'test',
            level: Level::Error,
            message: 'Error',
            context: [],
            extra: [],
        );

        $this->assertFalse(app(TelegramAlertService::class)->sendLogAlert($record));
        Http::assertNothingSent();
    }

    public function test_dedupes_identical_messages(): void
    {
        config(['telegram.dedupe_seconds' => 60]);
        Cache::flush();

        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true], 200),
        ]);

        $service = app(TelegramAlertService::class);
        $record = new LogRecord(
            datetime: new \DateTimeImmutable,
            channel: 'test',
            level: Level::Error,
            message: 'Duplicate error',
            context: [],
            extra: [],
        );

        $this->assertTrue($service->sendLogAlert($record));
        $this->assertFalse($service->sendLogAlert($record));

        Http::assertSentCount(1);
    }
}
