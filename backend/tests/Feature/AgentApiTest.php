<?php

namespace Tests\Feature;

use App\Models\DictionaryEntry;
use App\Models\User;
use App\Support\AgentToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AgentApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_app_token_can_create_list_and_revoke_agent_token(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['*']);

        $create = $this->postJson('/api/me/agent-tokens', []);
        $create->assertCreated()
            ->assertJsonPath('data.name', AgentToken::NAME)
            ->assertJsonStructure(['token', 'data' => ['id', 'abilities']]);

        $tokenId = $create->json('data.id');
        $this->assertNotEmpty($create->json('token'));

        $this->getJson('/api/me/agent-tokens')
            ->assertOk()
            ->assertJsonPath('data.0.id', $tokenId)
            ->assertJsonMissingPath('data.0.token');

        $this->deleteJson('/api/me/agent-tokens/'.$tokenId)
            ->assertOk();

        $this->getJson('/api/me/agent-tokens')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_agent_token_cannot_manage_agent_tokens(): void
    {
        $user = User::factory()->create();
        $agent = $user->createToken(AgentToken::NAME, AgentToken::defaultAbilities());

        $this->withToken($agent->plainTextToken)
            ->postJson('/api/me/agent-tokens', [])
            ->assertForbidden();
    }

    public function test_agent_lookup_save_and_curate(): void
    {
        Http::fake([
            'api.dictionaryapi.dev/*' => Http::response([[
                'word' => 'happy',
                'phonetic' => '/ˈhæpi/',
                'phonetics' => [],
                'meanings' => [[
                    'partOfSpeech' => 'adjective',
                    'definitions' => [[
                        'definition' => 'Feeling joy',
                        'example' => 'I am happy',
                    ]],
                ]],
            ]], 200),
            'api.datamuse.com/*' => Http::response([], 200),
        ]);

        $user = User::factory()->create();
        $agent = $user->createToken(AgentToken::NAME, AgentToken::defaultAbilities());
        $headers = ['Authorization' => 'Bearer '.$agent->plainTextToken];

        $this->withHeaders($headers)
            ->getJson('/api/agent/dictionary/happy')
            ->assertOk()
            ->assertJsonPath('word', 'happy')
            ->assertJsonPath('meanings.0.definition', 'Feeling joy');

        $save = $this->withHeaders($headers)
            ->postJson('/api/agent/vocabularies', [
                'word' => 'happy',
                'phonetic' => '/ˈhæpi/',
                'meanings' => [[
                    'part_of_speech' => 'adjective',
                    'definition' => 'Feeling joy',
                    'examples' => ['I am happy'],
                    'synonyms' => ['glad'],
                    'antonyms' => ['sad'],
                ]],
            ]);
        $save->assertCreated()->assertJsonPath('data.word', 'happy');
        $vocabId = $save->json('data.id');

        $this->withHeaders($headers)
            ->putJson('/api/agent/vocabularies/'.$vocabId, [
                'meanings' => [[
                    'part_of_speech' => 'adjective',
                    'definition' => 'Feeling good',
                    'examples' => ['I feel good'],
                    'synonyms' => ['glad'],
                    'antonyms' => ['sad'],
                ]],
            ])
            ->assertOk()
            ->assertJsonPath('data.meanings.0.definition', 'Feeling good');

        $curate = $this->withHeaders($headers)
            ->putJson('/api/agent/dictionary/happy', [
                'word' => 'happy',
                'phonetic' => '/ˈhæpi/',
                'meanings' => [[
                    'part_of_speech' => 'adjective',
                    'definition' => 'Feeling or showing pleasure',
                    'examples' => ['She was happy to see them.'],
                    'synonyms' => ['glad', 'joyful'],
                    'antonyms' => ['sad'],
                ]],
                'synonyms' => [],
                'antonyms' => [],
            ]);
        $curate->assertOk()
            ->assertJsonPath('data.curated', true)
            ->assertJsonPath('data.meanings.0.definition', 'Feeling or showing pleasure');

        $entry = DictionaryEntry::query()->where('word', 'happy')->first();
        $this->assertNotNull($entry);
        $this->assertTrue((bool) $entry->is_curated);

        // Curated dictionary entries cannot be edited via vocabulary update.
        $this->withHeaders($headers)
            ->putJson('/api/agent/vocabularies/'.$vocabId, [
                'meanings' => [['definition' => 'Should fail']],
            ])
            ->assertUnprocessable();
    }

    public function test_missing_ability_is_forbidden(): void
    {
        Http::fake([
            'api.dictionaryapi.dev/*' => Http::response([[
                'word' => 'cat',
                'meanings' => [[
                    'partOfSpeech' => 'noun',
                    'definitions' => [['definition' => 'A small animal']],
                ]],
            ]], 200),
            'api.datamuse.com/*' => Http::response([], 200),
        ]);

        $user = User::factory()->create();
        $lookupOnly = $user->createToken(AgentToken::NAME, [AgentToken::ABILITY_LOOKUP]);

        $this->withToken($lookupOnly->plainTextToken)
            ->getJson('/api/agent/dictionary/cat')
            ->assertOk();

        $this->withToken($lookupOnly->plainTextToken)
            ->postJson('/api/agent/vocabularies', ['word' => 'cat'])
            ->assertForbidden();

        $this->withToken($lookupOnly->plainTextToken)
            ->putJson('/api/agent/dictionary/cat', [
                'meanings' => [['definition' => 'A feline']],
            ])
            ->assertForbidden();
    }
}
