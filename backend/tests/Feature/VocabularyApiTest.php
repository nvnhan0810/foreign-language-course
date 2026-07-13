<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VocabularyApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_save_list_and_delete_vocabulary(): void
    {
        Http::fake([
            'api.dictionaryapi.dev/*' => Http::response([[
                'word' => 'ocean',
                'phonetic' => '/ˈəʊ.ʃən/',
                'phonetics' => [],
                'meanings' => [[
                    'partOfSpeech' => 'noun',
                    'definitions' => [['definition' => 'A large body of water', 'example' => 'The ocean is vast']],
                ]],
            ]], 200),
        ]);

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $create = $this->postJson('/api/vocabularies', ['word' => 'Ocean']);
        $create->assertCreated()
            ->assertJsonPath('data.word', 'ocean');

        $idempotent = $this->postJson('/api/vocabularies', ['word' => 'ocean']);
        $idempotent->assertOk()
            ->assertJsonPath('data.id', $create->json('data.id'));

        $this->getJson('/api/vocabularies')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $id = $create->json('data.id');
        $this->deleteJson('/api/vocabularies/'.$id)
            ->assertOk();

        $this->assertDatabaseMissing('vocabularies', ['id' => $id]);
    }
}
