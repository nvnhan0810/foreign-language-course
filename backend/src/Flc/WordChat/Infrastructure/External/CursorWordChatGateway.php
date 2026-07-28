<?php

namespace Flc\WordChat\Infrastructure\External;

use Flc\WordChat\Application\CursorWordChatGateway;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

final class CursorWordChatGateway implements CursorWordChatGateway
{
    public function isConfigured(): bool
    {
        return (bool) config('listening.cursor_api_key');
    }

    public function createAgent(string $prompt): ?array
    {
        $apiKey = config('listening.cursor_api_key');
        if (! $apiKey) {
            return null;
        }

        $baseUrl = $this->baseUrl();

        try {
            $response = $this->jsonClient($apiKey)->post("{$baseUrl}/v1/agents", [
                'prompt' => ['text' => $prompt],
                'name' => 'FLC Word Chat',
            ]);
        } catch (Throwable $e) {
            Log::warning('Word chat cursor agent create failed', ['error' => $e->getMessage()]);

            return null;
        }

        return $this->parseAgentRunIds($response);
    }

    public function followUp(string $agentId, string $prompt): ?array
    {
        $apiKey = config('listening.cursor_api_key');
        if (! $apiKey) {
            return null;
        }

        $baseUrl = $this->baseUrl();

        try {
            $response = $this->jsonClient($apiKey)->post("{$baseUrl}/v1/agents/{$agentId}/runs", [
                'prompt' => ['text' => $prompt],
            ]);
        } catch (Throwable $e) {
            Log::warning('Word chat cursor follow-up failed', [
                'agent_id' => $agentId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        if (! $response->successful()) {
            Log::warning('Word chat cursor follow-up rejected', [
                'agent_id' => $agentId,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        }

        $runId = $response->json('run.id');

        if (! is_string($runId) || $runId === '') {
            return null;
        }

        return ['agentId' => $agentId, 'runId' => $runId];
    }

    public function openRunStream(string $agentId, string $runId, ?string $lastEventId = null): ?Response
    {
        $apiKey = config('listening.cursor_api_key');
        if (! $apiKey) {
            return null;
        }

        $baseUrl = $this->baseUrl();
        $headers = ['Accept' => 'text/event-stream'];

        if ($lastEventId !== null && $lastEventId !== '') {
            $headers['Last-Event-ID'] = $lastEventId;
        }

        try {
            $response = $this->streamClient($apiKey)
                ->withOptions(['stream' => true])
                ->withHeaders($headers)
                ->get("{$baseUrl}/v1/agents/{$agentId}/runs/{$runId}/stream");
        } catch (Throwable $e) {
            Log::warning('Word chat cursor stream open failed', [
                'agent_id' => $agentId,
                'run_id' => $runId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        return $response->successful() ? $response : null;
    }

    public function getRun(string $agentId, string $runId): ?array
    {
        $apiKey = config('listening.cursor_api_key');
        if (! $apiKey) {
            return null;
        }

        $baseUrl = $this->baseUrl();

        try {
            $response = $this->jsonClient($apiKey)->get("{$baseUrl}/v1/agents/{$agentId}/runs/{$runId}");
        } catch (Throwable $e) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $status = $response->json('status');
        $result = $response->json('result');

        return [
            'status' => is_string($status) ? $status : 'UNKNOWN',
            'text' => is_string($result) ? $result : null,
        ];
    }

    public function archiveAgent(string $agentId): void
    {
        $apiKey = config('listening.cursor_api_key');
        if (! $apiKey) {
            return;
        }

        $baseUrl = $this->baseUrl();

        try {
            Http::withBasicAuth($apiKey, '')
                ->acceptJson()
                ->timeout(10)
                ->post("{$baseUrl}/v1/agents/{$agentId}/archive");
        } catch (Throwable $e) {
            Log::debug('Word chat cursor archive skipped', [
                'agent_id' => $agentId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('listening.cursor_api_base'), '/');
    }

    private function jsonClient(string $apiKey): PendingRequest
    {
        return $this->baseClient($apiKey, (int) config('word_chat.cursor_http_timeout_seconds', 60));
    }

    private function streamClient(string $apiKey): PendingRequest
    {
        $timeout = max(30, (int) config('word_chat.cursor_stream_timeout_seconds', 300));

        return $this->baseClient($apiKey, $timeout);
    }

    private function baseClient(string $apiKey, int $timeout): PendingRequest
    {
        $connectTimeout = max(3, (int) config('word_chat.cursor_connect_timeout_seconds', 10));
        $retries = max(0, (int) config('word_chat.cursor_http_retries', 1));

        $request = Http::withBasicAuth($apiKey, '')
            ->acceptJson()
            ->timeout($timeout)
            ->connectTimeout($connectTimeout);

        if ($retries > 0) {
            $request = $request->retry(
                $retries,
                1000,
                fn (Throwable $exception) => $exception instanceof ConnectionException,
                throw: false,
            );
        }

        return $request;
    }

    /**
     * @return array{agentId: string, runId: string}|null
     */
    private function parseAgentRunIds(Response $response): ?array
    {
        if (! $response->successful()) {
            Log::warning('Word chat cursor agent create rejected', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        }

        $agentId = $response->json('agent.id');
        $runId = $response->json('run.id');

        if (! is_string($agentId) || ! is_string($runId) || $agentId === '' || $runId === '') {
            return null;
        }

        return ['agentId' => $agentId, 'runId' => $runId];
    }
}
