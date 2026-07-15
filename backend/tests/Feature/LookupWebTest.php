<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LookupWebTest extends TestCase
{
    use RefreshDatabase;

    public function test_lookup_result_page_renders_when_meanings_include_array_fields(): void
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

        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('user.home.lookup.search'), [
            'word' => 'hello',
        ]);

        $response->assertRedirect(route('user.home.lookup'));

        $this->actingAs($user)
            ->get(route('user.home.lookup'))
            ->assertOk()
            ->assertSee('hello', false)
            ->assertSee('A greeting', false)
            ->assertSee('Synonyms', false)
            ->assertSee('Antonyms', false)
            ->assertSee('hi', false)
            ->assertSee('goodbye', false)
            ->assertSee('name="meanings[0][definition]"', false)
            ->assertSee('name="meanings[0][synonyms][]"', false)
            ->assertDontSee('name="meanings[0][examples]"', false)
            ->assertDontSee('data-dict-tab', false);
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
            ->assertSee('glad', false)
            ->assertSee('Pleased', false)
            ->assertSee('Save word', false);
    }
}
