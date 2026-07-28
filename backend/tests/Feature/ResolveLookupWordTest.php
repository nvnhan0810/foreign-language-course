<?php

namespace Tests\Feature;

use App\Models\User;
use Flc\Dictionary\Application\Query\ResolveLookupWord;
use Flc\Shared\Application\QueryBus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ResolveLookupWordTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_resolve_maps_plural_to_singular(): void
    {
        Http::fake([
            'api.dictionaryapi.dev/api/v2/entries/en/outlets*' => Http::response([], 404),
            'api.dictionaryapi.dev/api/v2/entries/en/outlet*' => Http::response([[
                'word' => 'outlet',
                'phonetics' => [],
                'meanings' => [[
                    'partOfSpeech' => 'noun',
                    'definitions' => [[
                        'definition' => 'A point of sale or exit for goods',
                    ]],
                ]],
            ]], 200),
            'api.datamuse.com/*' => Http::response([], 200),
        ]);

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/dictionary/resolve/outlets');

        $response->assertOk()
            ->assertJsonPath('selected', 'outlets')
            ->assertJsonPath('resolved', 'outlet')
            ->assertJsonPath('method', 'lemma_rules')
            ->assertJsonPath('dictionary.word', 'outlet');
    }

    public function test_resolve_keeps_exact_match_when_available(): void
    {
        Http::fake([
            'api.dictionaryapi.dev/*' => Http::response([[
                'word' => 'news',
                'phonetics' => [],
                'meanings' => [[
                    'partOfSpeech' => 'noun',
                    'definitions' => [[
                        'definition' => 'New information',
                    ]],
                ]],
            ]], 200),
            'api.datamuse.com/*' => Http::response([], 200),
        ]);

        $result = app(QueryBus::class)->ask(new ResolveLookupWord('news'));

        $this->assertNotNull($result);
        $this->assertSame('news', $result['selected']);
        $this->assertSame('news', $result['resolved']);
        $this->assertSame('exact', $result['method']);
    }

    public function test_resolve_returns_404_when_no_match(): void
    {
        Http::fake([
            'api.dictionaryapi.dev/*' => Http::response([], 404),
            'api.datamuse.com/*' => Http::response([], 200),
        ]);

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/dictionary/resolve/zzzznotaword')
            ->assertNotFound();
    }

    public function test_exact_dictionary_endpoint_unchanged(): void
    {
        Http::fake([
            'api.dictionaryapi.dev/*' => Http::response([[
                'word' => 'hello',
                'phonetics' => [],
                'meanings' => [[
                    'partOfSpeech' => 'noun',
                    'definitions' => [[
                        'definition' => 'A greeting',
                    ]],
                ]],
            ]], 200),
            'api.datamuse.com/*' => Http::response([], 200),
        ]);

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/dictionary/hello')
            ->assertOk()
            ->assertJsonPath('word', 'hello')
            ->assertJsonMissingPath('selected');
    }
}
