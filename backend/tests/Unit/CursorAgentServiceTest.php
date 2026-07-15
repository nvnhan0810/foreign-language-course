<?php

namespace Tests\Unit;

use Flc\Media\Infrastructure\External\CursorAgentService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CursorAgentServiceTest extends TestCase
{
    public function test_complete_returns_null_when_cursor_is_unreachable(): void
    {
        config([
            'listening.cursor_api_key' => 'test-key',
            'listening.cursor_api_base' => 'https://api.cursor.com',
            'listening.cursor_model' => 'composer-2.5',
            'listening.cursor_http_retries' => 0,
            'listening.cursor_http_timeout_seconds' => 5,
            'listening.cursor_connect_timeout_seconds' => 3,
        ]);

        Http::fake(function () {
            throw new ConnectionException('cURL error 28: Operation timed out');
        });

        $service = new CursorAgentService;

        $this->assertNull($service->complete('Summarize this lesson.'));
    }

    public function test_complete_returns_run_result_when_finished(): void
    {
        config([
            'listening.cursor_api_key' => 'test-key',
            'listening.cursor_api_base' => 'https://api.cursor.com',
            'listening.cursor_model' => 'composer-2.5',
            'listening.cursor_timeout_seconds' => 30,
            'listening.cursor_poll_interval_seconds' => 1,
            'listening.cursor_http_retries' => 0,
        ]);

        Http::fake([
            'api.cursor.com/v1/agents' => Http::response([
                'agent' => ['id' => 'agent_1'],
                'run' => ['id' => 'run_1'],
            ], 200),
            'api.cursor.com/v1/agents/agent_1/runs/run_1' => Http::response([
                'status' => 'FINISHED',
                'result' => '{"summary":"ok"}',
            ], 200),
            'api.cursor.com/v1/agents/agent_1/archive' => Http::response([], 200),
        ]);

        $service = new CursorAgentService;

        $this->assertSame('{"summary":"ok"}', $service->complete('Analyze this.'));
    }
}
