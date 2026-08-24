<?php

namespace Tests\Feature;

use App\Models\DictionaryEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LookupWebTest extends TestCase
{
    use RefreshDatabase;

    public function test_lookup_page_renders_dictionary_search_not_chat(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('user.home.lookup'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Learn/Index')
                ->where('word', '')
                ->where('result', null)
                ->where('saved', false)
            )
            ->assertDontSee('Ask about a word or phrase', false)
            ->assertDontSee('data-word-chat', false)
            ->assertDontSee('Preparing chat', false);
    }

    public function test_lookup_page_prefills_query_parameter(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('user.home.lookup', ['q' => 'outlet']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Learn/Index')
                ->where('word', 'outlet')
                ->where('result', null)
            );
    }

    public function test_lookup_miss_calls_dictionary_api_and_shows_result(): void
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
            'api.datamuse.com/*' => Http::response([], 200),
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('user.home.lookup.search'), ['word' => 'hello'])
            ->assertRedirect(route('user.home.lookup'));

        Http::assertSent(fn ($request) => str_contains($request->url(), 'dictionaryapi.dev'));

        $this->actingAs($user)
            ->get(route('user.home.lookup'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Learn/Index')
                ->where('word', 'hello')
                ->where('saved', false)
                ->where('result.word', 'hello')
                ->where('result.phonetic', '/həˈloʊ/')
                ->where('result.meanings.0.definition', 'A greeting')
                ->where('result.meanings.0.synonyms.0', 'hi')
                ->where('result.meanings.0.antonyms.0', 'goodbye')
            );
    }

    public function test_lookup_saved_word_reads_from_database_without_api(): void
    {
        $entry = DictionaryEntry::query()->create([
            'word' => 'custom',
            'phonetic' => '/k/',
            'audio_url' => null,
            'source' => 'admin',
            'is_curated' => true,
            'save_count' => 1,
        ]);
        $entry->meanings()->create([
            'part_of_speech' => 'noun',
            'definition' => 'FLC definition',
            'position' => 0,
        ]);

        Http::fake();

        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('user.home.lookup.search'), ['word' => 'Custom'])
            ->assertRedirect(route('user.home.lookup'));

        Http::assertNothingSent();

        $this->actingAs($user)
            ->get(route('user.home.lookup'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Learn/Index')
                ->where('result.word', 'custom')
                ->where('result.meanings.0.definition', 'FLC definition')
                ->where('result.curated', true)
            );
    }

    public function test_lookup_marks_result_saved_when_user_already_bookmarked_word(): void
    {
        Http::fake([
            'api.dictionaryapi.dev/*' => Http::response([[
                'word' => 'happy',
                'phonetic' => '/ˈhæpi/',
                'phonetics' => [],
                'meanings' => [[
                    'partOfSpeech' => 'adjective',
                    'definitions' => [['definition' => 'Feeling joy']],
                ]],
            ]], 200),
            'api.datamuse.com/*' => Http::response([], 200),
        ]);

        $user = User::factory()->create();
        $create = $this->actingAs($user)->postJson('/api/vocabularies', ['word' => 'happy']);
        $create->assertCreated();
        $vocabId = $create->json('data.id');

        Http::fake();

        $this->actingAs($user)
            ->post(route('user.home.lookup.search'), ['word' => 'happy'])
            ->assertRedirect(route('user.home.lookup'));

        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'dictionaryapi.dev'));

        $this->actingAs($user)
            ->get(route('user.home.lookup'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Learn/Index')
                ->where('saved', true)
                ->where('savedVocabularyId', $vocabId)
                ->where('result.meanings.0.definition', 'Feeling joy')
            );
    }

    public function test_save_word_from_lookup_persists_vocabulary(): void
    {
        Http::fake([
            'api.dictionaryapi.dev/*' => Http::response([[
                'word' => 'hello',
                'phonetic' => '/həˈloʊ/',
                'phonetics' => [],
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
            'api.datamuse.com/*' => Http::response([], 200),
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)->post(route('user.home.lookup.search'), ['word' => 'hello']);

        $this->actingAs($user)
            ->post(route('user.home.lookup.save'), [
                'word' => 'hello',
                'phonetic' => '/həˈloʊ/',
                'meanings' => [[
                    'part_of_speech' => 'noun',
                    'definition' => 'A greeting',
                    'example' => 'Hello there!',
                    'synonyms' => ['hi'],
                    'antonyms' => ['goodbye'],
                ]],
            ])
            ->assertRedirect(route('user.home.lookup'));

        $this->assertDatabaseHas('vocabularies', [
            'user_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->get(route('user.home.lookup'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('saved', true)
                ->where('flash.success', 'Word saved.')
            );
    }

    public function test_open_related_word_goes_to_vocab_detail_when_saved(): void
    {
        Http::fake([
            'api.dictionaryapi.dev/*' => Http::response([[
                'word' => 'happy',
                'meanings' => [[
                    'partOfSpeech' => 'adjective',
                    'definitions' => [['definition' => 'Feeling joy']],
                ]],
            ]], 200),
            'api.datamuse.com/*' => Http::response([], 200),
        ]);

        $user = User::factory()->create();
        $create = $this->actingAs($user)->postJson('/api/vocabularies', ['word' => 'happy']);
        $create->assertCreated();
        $vocabId = $create->json('data.id');

        $this->actingAs($user)
            ->get(route('user.home.word.open', ['word' => 'happy']))
            ->assertRedirect(route('user.home.vocab.show', $vocabId));
    }

    public function test_open_related_word_looks_up_when_not_saved(): void
    {
        Http::fake([
            'api.dictionaryapi.dev/*' => Http::response([[
                'word' => 'glad',
                'meanings' => [[
                    'partOfSpeech' => 'adjective',
                    'definitions' => [['definition' => 'Pleased']],
                ]],
            ]], 200),
            'api.datamuse.com/*' => Http::response([], 200),
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('user.home.word.open', ['word' => 'glad']))
            ->assertRedirect(route('user.home.lookup'));

        $this->actingAs($user)
            ->get(route('user.home.lookup'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Learn/Index')
                ->where('word', 'glad')
                ->where('saved', false)
                ->where('result.word', 'glad')
                ->where('result.meanings.0.definition', 'Pleased')
            );
    }

    public function test_session_user_can_list_word_chat_messages_via_api(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/word-chat/messages')
            ->assertOk()
            ->assertJsonPath('data', []);
    }

    public function test_pronounce_endpoint_returns_audio_url_json(): void
    {
        DictionaryEntry::query()->create([
            'word' => 'happy',
            'phonetic' => '/ˈhæpi/',
            'audio_url' => 'https://example.com/happy-us.mp3',
            'source' => 'admin',
            'is_curated' => true,
            'save_count' => 1,
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson(route('user.home.dictionary.pronounce', ['word' => 'happy']))
            ->assertOk()
            ->assertJsonPath('audio_url', 'https://example.com/happy-us.mp3');
    }

    public function test_pronounce_endpoint_returns_404_when_audio_missing(): void
    {
        DictionaryEntry::query()->create([
            'word' => 'silent',
            'audio_url' => null,
            'source' => 'admin',
            'is_curated' => true,
            'save_count' => 1,
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson(route('user.home.dictionary.pronounce', ['word' => 'silent']))
            ->assertNotFound();
    }
}
