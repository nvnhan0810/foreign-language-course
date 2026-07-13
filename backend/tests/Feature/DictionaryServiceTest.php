<?php

namespace Tests\Feature;

use App\Models\DictionaryEntry;
use App\Models\User;
use App\Services\DictionaryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DictionaryServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_lookup_miss_calls_api_but_does_not_persist(): void
    {
        Http::fake([
            'api.dictionaryapi.dev/*' => Http::response([[
                'word' => 'hello',
                'phonetic' => '/həˈloʊ/',
                'phonetics' => [['text' => '/həˈloʊ/', 'audio' => 'https://example.com/us.mp3']],
                'meanings' => [[
                    'partOfSpeech' => 'noun',
                    'synonyms' => ['hi'],
                    'antonyms' => ['goodbye'],
                    'definitions' => [[
                        'definition' => 'A greeting',
                        'example' => 'Hello there!',
                    ]],
                ]],
            ]], 200),
        ]);

        $service = app(DictionaryService::class);
        $result = $service->lookup('Hello');

        $this->assertNotNull($result);
        $this->assertSame('hello', $result['word']);
        $this->assertSame('A greeting', $result['meanings'][0]['definition']);
        $this->assertSame('Hello there!', $result['meanings'][0]['example']);
        $this->assertSame(['Hello there!'], $result['meanings'][0]['examples']);
        $this->assertDatabaseCount('dictionary_entries', 0);
        Http::assertSentCount(1);
    }

    public function test_upsert_on_save_persists_entry(): void
    {
        Http::fake([
            'api.dictionaryapi.dev/*' => Http::response([[
                'word' => 'bright',
                'phonetic' => '/braɪt/',
                'phonetics' => [],
                'meanings' => [[
                    'partOfSpeech' => 'adjective',
                    'synonyms' => ['smart'],
                    'antonyms' => ['dull'],
                    'definitions' => [[
                        'definition' => 'Giving much light',
                        'example' => 'A bright room',
                    ]],
                ]],
            ]], 200),
        ]);

        $service = app(DictionaryService::class);
        $payload = $service->lookup('bright');
        $entry = $service->upsertOnSave('bright', $payload);

        $this->assertNotNull($entry);
        $this->assertDatabaseHas('dictionary_entries', [
            'word' => 'bright',
            'save_count' => 1,
            'is_curated' => false,
        ]);
        $this->assertDatabaseCount('dictionary_meanings', 1);
        $this->assertDatabaseCount('dictionary_examples', 1);
    }

    public function test_lookup_prefers_my_dictionary_over_api(): void
    {
        $entry = DictionaryEntry::query()->create([
            'word' => 'custom',
            'phonetic' => '/k/',
            'audio_url' => null,
            'source' => 'admin',
            'is_curated' => true,
            'save_count' => 2,
        ]);
        $meaning = $entry->meanings()->create([
            'part_of_speech' => 'noun',
            'definition' => 'FLC definition',
            'position' => 0,
        ]);
        $meaning->examples()->create([
            'example' => 'Custom example',
            'position' => 0,
        ]);

        Http::fake();

        $result = app(DictionaryService::class)->lookup('custom');

        $this->assertSame('FLC definition', $result['meanings'][0]['definition']);
        $this->assertTrue($result['curated']);
        $this->assertSame('flc', $result['source']);
        Http::assertNothingSent();
    }

    public function test_curated_entry_is_not_overwritten_on_save(): void
    {
        $entry = DictionaryEntry::query()->create([
            'word' => 'run',
            'phonetic' => null,
            'source' => 'admin',
            'is_curated' => true,
            'save_count' => 1,
        ]);
        $entry->meanings()->create([
            'part_of_speech' => 'verb',
            'definition' => 'Admin definition',
            'position' => 0,
        ]);

        app(DictionaryService::class)->upsertOnSave('run', [
            'word' => 'run',
            'phonetic' => '/rʌn/',
            'audio_url' => null,
            'meanings' => [[
                'part_of_speech' => 'verb',
                'definition' => 'API definition should not win',
                'examples' => ['x'],
            ]],
            'synonyms' => [],
            'antonyms' => [],
            'source' => 'dictionaryapi.dev',
        ]);

        $entry->refresh();
        $this->assertSame(2, $entry->save_count);
        $this->assertSame('Admin definition', $entry->meanings()->first()->definition);
        $this->assertNull($entry->phonetic);
    }

    public function test_save_vocabulary_writes_my_dictionary(): void
    {
        Http::fake([
            'api.dictionaryapi.dev/*' => Http::response([[
                'word' => 'apple',
                'phonetic' => '/ˈæp.əl/',
                'phonetics' => [],
                'meanings' => [[
                    'partOfSpeech' => 'noun',
                    'definitions' => [['definition' => 'A fruit', 'example' => 'An apple a day']],
                ]],
            ]], 200),
        ]);

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/vocabularies', [
            'word' => 'apple',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('dictionary_entries', ['word' => 'apple', 'save_count' => 1]);
        $this->assertDatabaseHas('vocabularies', ['user_id' => $user->id, 'word' => 'apple']);
    }
}
