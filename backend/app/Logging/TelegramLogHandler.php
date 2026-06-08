<?php

namespace App\Logging;

use App\Services\TelegramAlertService;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;

class TelegramLogHandler extends AbstractProcessingHandler
{
    public function __construct(
        private readonly TelegramAlertService $telegram,
        int|string|Level $level = Level::Warning,
        bool $bubble = true,
    ) {
        parent::__construct($level, $bubble);
    }

    protected function write(LogRecord $record): void
    {
        $this->telegram->sendLogAlert($record);
    }
}
