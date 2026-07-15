<?php

namespace Tests\Feature;

use App\Models\DictionaryEntry;
use App\Models\DictionaryMeaning;
use App\Models\User;
use App\Models\Vocabulary;
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
            'api.datamuse.com/words*' => Http::sequence()
                ->push([['word' => 'sea'], ['word' => 'water']], 200)
                ->push([['word' => 'land']], 200),
        ]);

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $create = $this->postJson('/api/vocabularies', ['word' => 'Ocean']);
        $create->assertCreated()
            ->assertJsonPath('data.word', 'ocean')
            ->assertJsonPath('data.meanings.0.synonyms.0', 'sea')
            ->assertJsonPath('data.meanings.0.antonyms.0', 'land');

        $this->assertDatabaseHas('dictionary_entries', ['word' => 'ocean']);
        $entry = DictionaryEntry::query()->where('word', 'ocean')->first();
        $this->assertNotNull($entry);
        $this->assertDatabaseHas('vocabularies', [
            'user_id' => $user->id,
            'dictionary_entry_id' => $entry->id,
        ]);

        $storedMeanings = $create->json('data.meanings');
        $this->assertContains('sea', $storedMeanings[0]['synonyms'] ?? []);
        $this->assertContains('land', $storedMeanings[0]['antonyms'] ?? []);

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
        $this->assertDatabaseHas('dictionary_entries', ['word' => 'ocean']);
    }

    public function test_save_with_client_meanings_still_keeps_related_words_from_lookup(): void
    {
        Http::fake([
            'api.dictionaryapi.dev/*' => Http::response([[
                'word' => 'happy',
                'phonetic' => '/ˈhæpi/',
                'phonetics' => [],
                'meanings' => [[
                    'partOfSpeech' => 'adjective',
                    'definitions' => [['definition' => 'Feeling pleasure']],
                ]],
            ]], 200),
            'api.datamuse.com/words*' => Http::sequence()
                ->push([['word' => 'joyful']], 200)
                ->push([['word' => 'sad']], 200),
        ]);

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $create = $this->postJson('/api/vocabularies', [
            'word' => 'happy',
            'meanings' => [[
                'part_of_speech' => 'adjective',
                'definition' => 'Feeling pleasure',
                'example' => null,
            ]],
        ]);

        $create->assertCreated();
        $this->assertSame(['joyful'], $create->json('data.meanings.0.synonyms'));
        $this->assertSame(['sad'], $create->json('data.meanings.0.antonyms'));
    }

    public function test_re_save_backfills_missing_related_words_on_existing_vocabulary(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $entry = DictionaryEntry::query()->create([
            'word' => 'happy',
            'phonetic' => '/ˈhæpi/',
            'source' => 'user_save',
            'is_curated' => false,
            'save_count' => 1,
        ]);
        $meaning = DictionaryMeaning::query()->create([
            'dictionary_entry_id' => $entry->id,
            'part_of_speech' => 'adjective',
            'definition' => 'Feeling pleasure',
            'position' => 0,
        ]);
        unset($meaning);

        $vocab = Vocabulary::query()->create([
            'user_id' => $user->id,
            'dictionary_entry_id' => $entry->id,
        ]);

        Http::fake([
            'api.dictionaryapi.dev/*' => Http::response([[
                'word' => 'happy',
                'phonetic' => '/ˈhæpi/',
                'phonetics' => [],
                'meanings' => [[
                    'partOfSpeech' => 'adjective',
                    'definitions' => [['definition' => 'Feeling pleasure']],
                ]],
            ]], 200),
            'api.datamuse.com/words*' => Http::sequence()
                ->push([['word' => 'joyful'], ['word' => 'glad']], 200)
                ->push([['word' => 'sad']], 200),
        ]);

        $response = $this->postJson('/api/vocabularies', ['word' => 'happy']);
        $response->assertOk()
            ->assertJsonPath('data.id', $vocab->id)
            ->assertJsonPath('data.meanings.0.synonyms.0', 'joyful')
            ->assertJsonPath('data.meanings.0.antonyms.0', 'sad');
    }
}
