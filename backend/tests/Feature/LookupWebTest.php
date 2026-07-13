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
            ->assertSee('Meanings', false)
            ->assertSee('Synonyms', false)
            ->assertSee('name="meanings[0][definition]"', false)
            ->assertSee('name="meanings[0][synonyms][]"', false)
            ->assertDontSee('name="meanings[0][examples]"', false);
    }
}
