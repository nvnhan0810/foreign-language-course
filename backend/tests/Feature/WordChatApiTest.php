<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WordChatMessage;
use App\Models\WordChatRun;
use Flc\WordChat\Application\CursorWordChatGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WordChatApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'listening.cursor_api_key' => 'test-key',
            'listening.cursor_api_base' => 'https://api.cursor.com',
        ]);
    }

    public function test_post_message_starts_run_and_returns_stream_url(): void
    {
        $gateway = \Mockery::mock(CursorWordChatGateway::class);
        $gateway->shouldReceive('isConfigured')->andReturn(true);
        $gateway->shouldReceive('createAgent')->once()->andReturn([
            'agentId' => 'agent_1',
            'runId' => 'run_1',
        ]);
        $this->app->instance(CursorWordChatGateway::class, $gateway);

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/word-chat/messages', [
            'text' => 'What does happy mean?',
        ]);

        $response->assertAccepted()
            ->assertJsonPath('data.run_id', 'run_1')
            ->assertJsonPath('data.stream_url', '/api/word-chat/stream/run_1');

        $this->assertDatabaseHas('word_chat_messages', [
            'user_id' => $user->id,
            'role' => 'user',
            'content' => 'What does happy mean?',
            'cursor_run_id' => 'run_1',
        ]);

        $this->assertDatabaseHas('word_chat_runs', [
            'user_id' => $user->id,
            'cursor_run_id' => 'run_1',
            'status' => 'streaming',
        ]);
    }

    public function test_follow_up_reuses_existing_agent(): void
    {
        $gateway = \Mockery::mock(CursorWordChatGateway::class);
        $gateway->shouldReceive('isConfigured')->andReturn(true);
        $gateway->shouldReceive('followUp')
            ->once()
            ->with('agent_1', \Mockery::type('string'))
            ->andReturn(['agentId' => 'agent_1', 'runId' => 'run_2']);
        $this->app->instance(CursorWordChatGateway::class, $gateway);

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $user->wordChatAgents()->create([
            'cursor_agent_id' => 'agent_1',
            'status' => 'active',
        ]);

        $this->postJson('/api/word-chat/messages', [
            'text' => 'Give me an example sentence.',
        ])->assertAccepted()
            ->assertJsonPath('data.run_id', 'run_2');
    }

    public function test_stream_persists_assistant_message(): void
    {
        $gateway = \Mockery::mock(CursorWordChatGateway::class);
        $gateway->shouldReceive('openRunStream')
            ->once()
            ->andReturnUsing(function () {
                $body = "event: assistant\ndata: {\"text\":\"Happy means feeling joy.\"}\n\n"
                    ."event: done\ndata: {}\n\n";

                return new \Illuminate\Http\Client\Response(
                    new \GuzzleHttp\Psr7\Response(200, ['Content-Type' => 'text/event-stream'], $body)
                );
            });
        $gateway->shouldReceive('getRun')->never();
        $this->app->instance(CursorWordChatGateway::class, $gateway);

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $agent = $user->wordChatAgents()->create([
            'cursor_agent_id' => 'agent_1',
            'status' => 'active',
        ]);

        $userMessage = WordChatMessage::query()->create([
            'user_id' => $user->id,
            'role' => 'user',
            'content' => 'What does happy mean?',
            'cursor_run_id' => 'run_1',
        ]);

        WordChatRun::query()->create([
            'user_id' => $user->id,
            'word_chat_agent_id' => $agent->id,
            'cursor_agent_id' => 'agent_1',
            'cursor_run_id' => 'run_1',
            'user_message_id' => $userMessage->id,
            'status' => 'streaming',
        ]);

        $response = $this->get('/api/word-chat/stream/run_1', [
            'Accept' => 'text/event-stream',
        ]);

        $response->assertOk();
        $this->assertStringContainsString('Happy means feeling joy.', $response->getContent());

        $this->assertDatabaseHas('word_chat_messages', [
            'user_id' => $user->id,
            'role' => 'assistant',
            'content' => 'Happy means feeling joy.',
            'cursor_run_id' => 'run_1',
        ]);

        $this->assertDatabaseHas('word_chat_runs', [
            'cursor_run_id' => 'run_1',
            'status' => 'finished',
        ]);
    }

    public function test_list_messages_returns_history(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        WordChatMessage::query()->create([
            'user_id' => $user->id,
            'role' => 'user',
            'content' => 'Hello',
        ]);

        $this->getJson('/api/word-chat/messages')
            ->assertOk()
            ->assertJsonPath('data.0.content', 'Hello')
            ->assertJsonPath('data.0.role', 'user');
    }

    public function test_reset_archives_agent(): void
    {
        $gateway = \Mockery::mock(CursorWordChatGateway::class);
        $gateway->shouldReceive('archiveAgent')->once()->with('agent_1');
        $this->app->instance(CursorWordChatGateway::class, $gateway);

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $user->wordChatAgents()->create([
            'cursor_agent_id' => 'agent_1',
            'status' => 'active',
        ]);

        $this->postJson('/api/word-chat/reset')
            ->assertOk()
            ->assertJsonPath('data.reset', true);

        $this->assertDatabaseHas('word_chat_agents', [
            'user_id' => $user->id,
            'status' => 'archived',
        ]);
    }
}
