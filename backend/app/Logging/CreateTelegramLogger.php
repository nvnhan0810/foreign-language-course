<?php

namespace App\Logging;

use App\Services\TelegramAlertService;
use Monolog\Handler\NullHandler;
use Monolog\Logger;

class CreateTelegramLogger
{
    /**
     * @param  array{level?: string}  $config
     */
    public function __invoke(array $config): Logger
    {
        $telegram = app(TelegramAlertService::class);

        if (! $telegram->isConfigured()) {
            return new Logger('telegram', [new NullHandler]);
        }

        $level = Logger::toMonologLevel($config['level'] ?? 'warning');

        return new Logger('telegram', [
            new TelegramLogHandler($telegram, $level),
        ]);
    }
}
