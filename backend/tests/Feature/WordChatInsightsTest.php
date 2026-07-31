<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Vocabulary;
use App\Models\VocabularyLearningInsight;
use App\Models\WordChatMessage;
use App\Models\WordChatRun;
use App\Models\DictionaryEntry;
use App\Models\DictionaryMeaning;
use App\Models\DictionaryExample;
use Flc\WordChat\Application\CursorWordChatGateway;
use Flc\WordChat\Application\LearningInsightRepository;
use Flc\WordChat\Application\WordChatInsightExtractor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WordChatInsightsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'listening.cursor_api_key' => 'test-key',
            'listening.cursor_api_base' => 'https://api.cursor.com',
            'word_chat.prompt_version' => 2,
        ]);
    }

    private function createReadyAgent(User $user, string $cursorAgentId = 'agent_1'): \App\Models\WordChatAgent
    {
        return $user->wordChatAgents()->create([
            'cursor_agent_id' => $cursorAgentId,
            'status' => 'active',
            'prompt_version' => 2,
        ]);
    }

    public function test_insight_extractor_strips_json_block_and_parses_save_vocab(): void
    {
        $user = User::factory()->create();
        $extractor = app(WordChatInsightExtractor::class);

        $result = $extractor->extract(
            userId: $user->id,
            userQuestion: 'Save happy for me',
            assistantReply: "Done — I've added it to your vocabulary.\n\n```json\n{\"insights\":[{\"word\":\"happy\",\"type\":\"meaning\",\"content\":\"Feeling joy.\"}],\"save_vocab\":{\"word\":\"happy\"}}\n```",
            sourceMessageId: null,
        );

        $this->assertSame('happy', $result['save_vocab']?->word);
        $this->assertSame([], $result['save_vocab']?->examples ?? []);
        $this->assertCount(1, $result['insights']);
    }

    public function test_insight_extractor_parses_save_vocab_examples(): void
    {
        $user = User::factory()->create();
        $extractor = app(WordChatInsightExtractor::class);

        $result = $extractor->extract(
            userId: $user->id,
            userQuestion: 'Save these examples for penal',
            assistantReply: "Saved.\n\n```json\n{\"save_vocab\":{\"word\":\"penal\",\"examples\":[\"A penal offence can lead to imprisonment.\",\"Penal laws set out crimes and penalties.\"]}}\n```",
            sourceMessageId: null,
        );

        $this->assertSame('penal', $result['save_vocab']?->word);
        $this->assertSame([
            'A penal offence can lead to imprisonment.',
            'Penal laws set out crimes and penalties.',
        ], $result['save_vocab']?->examples ?? []);
    }

    public function test_insight_extractor_parses_full_save_vocab_payload(): void
    {
        $user = User::factory()->create();
        $extractor = app(WordChatInsightExtractor::class);

        $result = $extractor->extract(
            userId: $user->id,
            userQuestion: 'Save penal with full details',
            assistantReply: "Saved.\n\n```json\n".json_encode([
                'save_vocab' => [
                    'word' => 'penal',
                    'phonetic' => '/ˈpiːnəl/',
                    'meanings' => [[
                        'part_of_speech' => 'adjective',
                        'definition' => 'Relating to punishment by law',
                        'examples' => ['A penal offence can lead to imprisonment.'],
                        'synonyms' => ['punitive'],
                        'antonyms' => ['lenient'],
                    ]],
                    'synonyms' => ['legal'],
                    'antonyms' => [],
                ],
            ], JSON_UNESCAPED_UNICODE)."\n```",
            sourceMessageId: null,
        );

        $save = $result['save_vocab'];
        $this->assertSame('penal', $save?->word);
        $this->assertSame('/ˈpiːnəl/', $save?->phonetic);
        $this->assertSame('adjective', $save?->meanings[0]['part_of_speech'] ?? null);
        $this->assertSame(['punitive'], $save?->meanings[0]['synonyms'] ?? null);
        $this->assertSame(['legal'], $save?->synonyms);
    }

    public function test_stream_saves_vocabulary_when_user_requests_save(): void
    {
        $gateway = \Mockery::mock(CursorWordChatGateway::class);
        $gateway->shouldReceive('openRunStream')
            ->once()
            ->andReturnUsing(function () {
                $assistantText = "Saved happy to your vocabulary.\n\n```json\n"
                    .json_encode([
                        'insights' => [['word' => 'happy', 'type' => 'meaning', 'content' => 'Feeling joy.']],
                        'save_vocab' => ['word' => 'happy'],
                    ], JSON_UNESCAPED_UNICODE)
                    ."\n```";
                $body = 'event: assistant'
                    ."\ndata: ".json_encode(['text' => $assistantText], JSON_UNESCAPED_UNICODE)
                    ."\n\n"
                    ."event: done\ndata: {}\n\n";

                return new \Illuminate\Http\Client\Response(
                    new \GuzzleHttp\Psr7\Response(200, ['Content-Type' => 'text/event-stream'], $body)
                );
            });
        $this->app->instance(CursorWordChatGateway::class, $gateway);

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        DictionaryEntry::query()->create([
            'word' => 'happy',
            'source' => 'seed',
            'is_curated' => false,
            'save_count' => 0,
        ]);
        DictionaryMeaning::query()->create([
            'dictionary_entry_id' => DictionaryEntry::query()->where('word', 'happy')->value('id'),
            'definition' => 'Feeling joy.',
            'position' => 0,
        ]);

        $agent = $this->createReadyAgent($user);

        $userMessage = WordChatMessage::query()->create([
            'user_id' => $user->id,
            'role' => 'user',
            'content' => 'save this word',
            'cursor_run_id' => 'run_save_1',
        ]);

        WordChatMessage::query()->create([
            'user_id' => $user->id,
            'role' => 'user',
            'content' => 'happy',
            'cursor_run_id' => 'run_lookup',
        ]);

        WordChatRun::query()->create([
            'user_id' => $user->id,
            'word_chat_agent_id' => $agent->id,
            'cursor_agent_id' => 'agent_1',
            'cursor_run_id' => 'run_save_1',
            'user_message_id' => $userMessage->id,
            'status' => 'streaming',
        ]);

        $response = $this->get('/api/word-chat/stream/run_save_1', [
            'Accept' => 'text/event-stream',
        ]);

        $response->assertOk();
        $this->assertStringContainsString('event: vocab_saved', $response->streamedContent());

        $this->assertDatabaseHas('vocabularies', [
            'user_id' => $user->id,
        ]);

        $this->assertDatabaseHas('vocabulary_learning_insights', [
            'user_id' => $user->id,
            'word' => 'happy',
        ]);
    }

    public function test_insight_extractor_strips_json_block_and_parses_items(): void
    {
        $user = User::factory()->create();
        $extractor = app(WordChatInsightExtractor::class);

        $result = $extractor->extract(
            userId: $user->id,
            userQuestion: 'What does outlet mean?',
            assistantReply: "An outlet is a shop.\n\n```json\n{\"insights\":[{\"word\":\"outlet\",\"type\":\"meaning\",\"content\":\"A shop or store that sells goods.\"}]}\n```",
            sourceMessageId: null,
        );

        $this->assertStringNotContainsString('```json', $result['content']);
        $this->assertCount(1, $result['insights']);
        $this->assertSame('outlet', $result['insights'][0]->word);
        $this->assertSame('meaning', $result['insights'][0]->insightType);
    }

    public function test_stream_persists_learning_insights(): void
    {
        $gateway = \Mockery::mock(CursorWordChatGateway::class);
        $gateway->shouldReceive('openRunStream')
            ->once()
            ->andReturnUsing(function () {
                $body = "event: assistant\ndata: {\"text\":\"Outlet means a retail store.\\n\\n```json\\n{\\\"insights\\\":[{\\\"word\\\":\\\"outlet\\\",\\\"type\\\":\\\"usage\\\",\\\"content\\\":\\\"A retail store where goods are sold.\\\"}]}\\n```\"}\n\n"
                    ."event: done\ndata: {}\n\n";

                return new \Illuminate\Http\Client\Response(
                    new \GuzzleHttp\Psr7\Response(200, ['Content-Type' => 'text/event-stream'], $body)
                );
            });
        $this->app->instance(CursorWordChatGateway::class, $gateway);

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $agent = $this->createReadyAgent($user);

        $userMessage = WordChatMessage::query()->create([
            'user_id' => $user->id,
            'role' => 'user',
            'content' => 'What does outlet mean?',
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
        $this->assertStringContainsString('event: insights', $response->streamedContent());

        $this->assertDatabaseHas('vocabulary_learning_insights', [
            'user_id' => $user->id,
            'word' => 'outlet',
            'insight_type' => 'usage',
        ]);

        $assistant = WordChatMessage::query()
            ->where('user_id', $user->id)
            ->where('role', 'assistant')
            ->first();

        $this->assertNotNull($assistant);
        $this->assertStringNotContainsString('```json', $assistant->content);
    }

    public function test_stream_still_saves_assistant_message_when_insight_persistence_fails(): void
    {
        $insights = \Mockery::mock(LearningInsightRepository::class);
        $insights->shouldReceive('save')->andThrow(new \RuntimeException('insights unavailable'));
        $this->app->instance(LearningInsightRepository::class, $insights);

        $gateway = \Mockery::mock(CursorWordChatGateway::class);
        $gateway->shouldReceive('openRunStream')
            ->once()
            ->andReturnUsing(function () {
                $body = "event: assistant\ndata: {\"text\":\"MUD means a multiplayer dungeon game.\\n\\n```json\\n{\\\"insights\\\":[{\\\"word\\\":\\\"mud\\\",\\\"type\\\":\\\"meaning\\\",\\\"content\\\":\\\"Multi-User Dungeon\\\"}]}\\n```\"}\n\n"
                    ."event: done\ndata: {}\n\n";

                return new \Illuminate\Http\Client\Response(
                    new \GuzzleHttp\Psr7\Response(200, ['Content-Type' => 'text/event-stream'], $body)
                );
            });
        $this->app->instance(CursorWordChatGateway::class, $gateway);

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $agent = $this->createReadyAgent($user);

        $userMessage = WordChatMessage::query()->create([
            'user_id' => $user->id,
            'role' => 'user',
            'content' => 'What does MUD mean here?',
            'cursor_run_id' => 'run_2',
        ]);

        WordChatRun::query()->create([
            'user_id' => $user->id,
            'word_chat_agent_id' => $agent->id,
            'cursor_agent_id' => 'agent_1',
            'cursor_run_id' => 'run_2',
            'user_message_id' => $userMessage->id,
            'status' => 'streaming',
        ]);

        $response = $this->get('/api/word-chat/stream/run_2', [
            'Accept' => 'text/event-stream',
        ]);

        $response->assertOk();
        $this->assertStringContainsString('event: saved', $response->streamedContent());
        $this->assertStringNotContainsString('event: insights', $response->streamedContent());

        $this->assertDatabaseHas('word_chat_messages', [
            'user_id' => $user->id,
            'role' => 'assistant',
            'content' => 'MUD means a multiplayer dungeon game.',
        ]);

        $this->assertDatabaseHas('word_chat_runs', [
            'cursor_run_id' => 'run_2',
            'status' => 'finished',
        ]);
    }

    public function test_list_insights_endpoint(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        VocabularyLearningInsight::query()->create([
            'user_id' => $user->id,
            'word' => 'happy',
            'insight_type' => 'meaning',
            'content' => 'Feeling joy.',
        ]);

        $this->getJson('/api/word-chat/insights?word=happy')
            ->assertOk()
            ->assertJsonPath('data.0.word', 'happy')
            ->assertJsonPath('data.0.insight_type', 'meaning');
    }

    public function test_quiz_uses_saved_vocabulary_meanings_instead_of_insights(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $words = ['alpha', 'beta', 'gamma', 'delta'];
        $vocabularies = collect();

        foreach ($words as $index => $word) {
            $entry = DictionaryEntry::query()->create([
                'word' => $word,
                'source' => 'user_save',
                'is_curated' => false,
                'save_count' => 1,
            ]);
            DictionaryMeaning::query()->create([
                'dictionary_entry_id' => $entry->id,
                'definition' => "Definition of {$word}",
                'position' => 0,
            ]);
            $vocabularies->push(Vocabulary::query()->create([
                'user_id' => $user->id,
                'dictionary_entry_id' => $entry->id,
            ]));
        }

        $target = $vocabularies->first();

        $insight = VocabularyLearningInsight::query()->create([
            'user_id' => $user->id,
            'vocabulary_id' => $target->id,
            'word' => 'alpha',
            'insight_type' => 'usage',
            'content' => 'From chat: alpha is the first letter.',
        ]);

        $response = $this->getJson(
            '/api/quiz/next?vocabulary_id='.$target->id.'&insight_id='.$insight->id,
        )->assertOk();

        $this->assertContains($response->json('data.question_type'), [
            'definition_to_word',
            'word_to_definition',
        ]);
        $this->assertSame($target->id, $response->json('data.vocabulary_id'));
        $this->assertNull($response->json('data.insight_id'));
        $this->assertNotSame($insight->content, $response->json('data.prompt'));
    }

    public function test_stream_updates_existing_vocabulary_examples_from_save_vocab(): void
    {
        $gateway = \Mockery::mock(CursorWordChatGateway::class);
        $gateway->shouldReceive('openRunStream')
            ->once()
            ->andReturnUsing(function () {
                $assistantText = "Saved the examples to penal.\n\n```json\n"
                    .json_encode([
                        'save_vocab' => [
                            'word' => 'penal',
                            'examples' => [
                                'A penal offence can lead to imprisonment.',
                                'Penal laws set out crimes and penalties.',
                            ],
                        ],
                    ], JSON_UNESCAPED_UNICODE)
                    ."\n```";
                $body = 'event: assistant'
                    ."\ndata: ".json_encode(['text' => $assistantText], JSON_UNESCAPED_UNICODE)
                    ."\n\n"
                    ."event: done\ndata: {}\n\n";

                return new \Illuminate\Http\Client\Response(
                    new \GuzzleHttp\Psr7\Response(200, ['Content-Type' => 'text/event-stream'], $body)
                );
            });
        $this->app->instance(CursorWordChatGateway::class, $gateway);

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $entry = DictionaryEntry::query()->create([
            'word' => 'penal',
            'source' => 'user_save',
            'is_curated' => false,
            'save_count' => 1,
        ]);
        $meaning = DictionaryMeaning::query()->create([
            'dictionary_entry_id' => $entry->id,
            'definition' => 'Relating to punishment.',
            'position' => 0,
        ]);
        DictionaryExample::query()->create([
            'dictionary_meaning_id' => $meaning->id,
            'example' => 'Old example.',
            'position' => 0,
        ]);
        Vocabulary::query()->create([
            'user_id' => $user->id,
            'dictionary_entry_id' => $entry->id,
        ]);

        $agent = $this->createReadyAgent($user);

        $userMessage = WordChatMessage::query()->create([
            'user_id' => $user->id,
            'role' => 'user',
            'content' => 'Can you update penal in vocabulary with these examples?',
            'cursor_run_id' => 'run_update_examples',
        ]);

        WordChatRun::query()->create([
            'user_id' => $user->id,
            'word_chat_agent_id' => $agent->id,
            'cursor_agent_id' => 'agent_1',
            'cursor_run_id' => 'run_update_examples',
            'user_message_id' => $userMessage->id,
            'status' => 'streaming',
        ]);

        $response = $this->get('/api/word-chat/stream/run_update_examples', [
            'Accept' => 'text/event-stream',
        ]);

        $response->assertOk();
        $this->assertStringContainsString('event: vocab_saved', $response->streamedContent());

        $this->assertDatabaseHas('dictionary_examples', [
            'example' => 'A penal offence can lead to imprisonment.',
        ]);
        $this->assertDatabaseHas('dictionary_examples', [
            'example' => 'Penal laws set out crimes and penalties.',
        ]);
        $this->assertDatabaseHas('dictionary_examples', [
            'example' => 'Old example.',
        ]);
    }

    public function test_stream_backfills_examples_from_previous_assistant_message_when_json_omits_them(): void
    {
        $gateway = \Mockery::mock(CursorWordChatGateway::class);
        $gateway->shouldReceive('openRunStream')
            ->once()
            ->andReturnUsing(function () {
                $assistantText = "Saved those examples to your penal vocabulary.\n\n```json\n"
                    .json_encode(['save_vocab' => ['word' => 'penal']], JSON_UNESCAPED_UNICODE)
                    ."\n```";
                $body = 'event: assistant'
                    ."\ndata: ".json_encode(['text' => $assistantText], JSON_UNESCAPED_UNICODE)
                    ."\n\n"
                    ."event: done\ndata: {}\n\n";

                return new \Illuminate\Http\Client\Response(
                    new \GuzzleHttp\Psr7\Response(200, ['Content-Type' => 'text/event-stream'], $body)
                );
            });
        $this->app->instance(CursorWordChatGateway::class, $gateway);

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $entry = DictionaryEntry::query()->create([
            'word' => 'penal',
            'source' => 'user_save',
            'is_curated' => false,
            'save_count' => 1,
        ]);
        $meaning = DictionaryMeaning::query()->create([
            'dictionary_entry_id' => $entry->id,
            'definition' => 'Relating to punishment.',
            'position' => 0,
        ]);
        DictionaryExample::query()->create([
            'dictionary_meaning_id' => $meaning->id,
            'example' => 'Old example.',
            'position' => 0,
        ]);
        Vocabulary::query()->create([
            'user_id' => $user->id,
            'dictionary_entry_id' => $entry->id,
        ]);

        $agent = $this->createReadyAgent($user);

        WordChatMessage::query()->create([
            'user_id' => $user->id,
            'role' => 'assistant',
            'content' => <<<'TEXT'
More examples:

Penal code — The country's penal code lists crimes and their punishments.
Penal colony — In the past, some countries sent prisoners to a penal colony.
TEXT,
            'cursor_run_id' => 'run_examples',
        ]);

        $userMessage = WordChatMessage::query()->create([
            'user_id' => $user->id,
            'role' => 'user',
            'content' => 'save these examples to "penal" vocabulary',
            'cursor_run_id' => 'run_save_examples_fallback',
        ]);

        WordChatRun::query()->create([
            'user_id' => $user->id,
            'word_chat_agent_id' => $agent->id,
            'cursor_agent_id' => 'agent_1',
            'cursor_run_id' => 'run_save_examples_fallback',
            'user_message_id' => $userMessage->id,
            'status' => 'streaming',
        ]);

        $response = $this->get('/api/word-chat/stream/run_save_examples_fallback', [
            'Accept' => 'text/event-stream',
        ]);

        $response->assertOk();
        $streamed = $response->streamedContent();
        $this->assertStringContainsString('event: saved', $streamed, $streamed);
        $this->assertStringContainsString('"content_updated":true', $streamed, $streamed);

        $this->assertDatabaseHas('dictionary_examples', [
            'example' => 'Penal code — The country\'s penal code lists crimes and their punishments.',
        ]);
        $this->assertDatabaseHas('dictionary_examples', [
            'example' => 'Penal colony — In the past, some countries sent prisoners to a penal colony.',
        ]);
    }

    public function test_complete_word_chat_run_backfills_examples_from_previous_assistant_message(): void
    {
        $user = User::factory()->create();

        $entry = DictionaryEntry::query()->create([
            'word' => 'penal',
            'source' => 'user_save',
            'is_curated' => false,
            'save_count' => 1,
        ]);
        $meaning = DictionaryMeaning::query()->create([
            'dictionary_entry_id' => $entry->id,
            'definition' => 'Relating to punishment.',
            'position' => 0,
        ]);
        DictionaryExample::query()->create([
            'dictionary_meaning_id' => $meaning->id,
            'example' => 'Old example.',
            'position' => 0,
        ]);
        Vocabulary::query()->create([
            'user_id' => $user->id,
            'dictionary_entry_id' => $entry->id,
        ]);

        $agent = $this->createReadyAgent($user);

        WordChatMessage::query()->create([
            'user_id' => $user->id,
            'role' => 'assistant',
            'content' => <<<'TEXT'
More examples:

Penal code — The country's penal code lists crimes and their punishments.
Penal colony — In the past, some countries sent prisoners to a penal colony.
TEXT,
            'cursor_run_id' => 'run_examples',
        ]);

        $userMessage = WordChatMessage::query()->create([
            'user_id' => $user->id,
            'role' => 'user',
            'content' => 'save these examples to "penal" vocabulary',
            'cursor_run_id' => 'run_save_examples_fallback',
        ]);

        WordChatRun::query()->create([
            'user_id' => $user->id,
            'word_chat_agent_id' => $agent->id,
            'cursor_agent_id' => 'agent_1',
            'cursor_run_id' => 'run_save_examples_fallback',
            'user_message_id' => $userMessage->id,
            'status' => 'streaming',
        ]);

        $assistantText = "Saved those examples to your penal vocabulary.\n\n```json\n"
            .json_encode(['save_vocab' => ['word' => 'penal']], JSON_UNESCAPED_UNICODE)
            ."\n```";

        $result = app(\Flc\Shared\Application\CommandBus::class)->dispatch(
            new \Flc\WordChat\Application\Command\CompleteWordChatRun(
                userId: $user->id,
                cursorRunId: 'run_save_examples_fallback',
                assistantContent: $assistantText,
            ),
        );

        $this->assertIsArray($result);
        $this->assertTrue($result['saved_vocabulary']['content_updated'] ?? false);
        $this->assertDatabaseHas('dictionary_examples', [
            'example' => 'Penal code — The country\'s penal code lists crimes and their punishments.',
        ]);
    }

    public function test_vocabulary_saver_backfills_examples_from_previous_assistant_message(): void
    {
        $user = User::factory()->create();

        $entry = DictionaryEntry::query()->create([
            'word' => 'penal',
            'source' => 'user_save',
            'is_curated' => false,
            'save_count' => 1,
        ]);
        $meaning = DictionaryMeaning::query()->create([
            'dictionary_entry_id' => $entry->id,
            'definition' => 'Relating to punishment.',
            'position' => 0,
        ]);
        DictionaryExample::query()->create([
            'dictionary_meaning_id' => $meaning->id,
            'example' => 'Old example.',
            'position' => 0,
        ]);
        Vocabulary::query()->create([
            'user_id' => $user->id,
            'dictionary_entry_id' => $entry->id,
        ]);

        WordChatMessage::query()->create([
            'user_id' => $user->id,
            'role' => 'assistant',
            'content' => <<<'TEXT'
More examples:

Penal code — The country's penal code lists crimes and their punishments.
Penal colony — In the past, some countries sent prisoners to a penal colony.
TEXT,
        ]);

        $userMessage = WordChatMessage::query()->create([
            'user_id' => $user->id,
            'role' => 'user',
            'content' => 'save these examples to "penal" vocabulary',
        ]);

        $saver = app(\Flc\WordChat\Application\WordChatVocabularySaver::class);
        $result = $saver->maybeSave(
            userId: $user->id,
            userQuestion: $userMessage->content,
            saveVocabFromAgent: \Flc\WordChat\Application\WordChatSaveVocabRequest::fromPayload(['word' => 'penal']),
            beforeMessageId: $userMessage->id,
            assistantReply: 'Saved those examples to your penal vocabulary.',
        );

        $this->assertNotNull($result);
        $this->assertTrue($result['content_updated'] ?? false);
        $this->assertDatabaseHas('dictionary_examples', [
            'example' => 'Penal code — The country\'s penal code lists crimes and their punishments.',
        ]);
    }
}
