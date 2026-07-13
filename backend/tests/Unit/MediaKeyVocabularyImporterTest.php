<?php

namespace Tests\Unit;

use App\Models\MediaItem;
use App\Models\User;
use App\Models\Vocabulary;
use App\Services\DictionaryService;
use App\Services\MediaKeyVocabularyImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class MediaKeyVocabularyImporterTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_imports_key_vocabulary_for_media_owner(): void
    {
        $user = User::factory()->create();
        $mediaItem = MediaItem::query()->create([
            'user_id' => $user->id,
            'title' => 'Climate change talk',
            'url' => 'https://example.com/video',
            'type' => 'youtube',
            'frequency' => 'weekly',
        ]);

        $dictionary = Mockery::mock(DictionaryService::class);
        $dictionary->shouldReceive('lookup')
            ->twice()
            ->andReturn([
                'word' => 'placeholder',
                'phonetic' => null,
                'meanings' => [],
            ]);
        $dictionary->shouldReceive('meaningsForVocabulary')
            ->twice()
            ->andReturnUsing(fn (array $meanings) => $meanings);
        $dictionary->shouldReceive('upsertOnSave')->twice()->andReturn(null);

        $importer = new MediaKeyVocabularyImporter($dictionary);

        $result = $importer->importFromAnalysis($mediaItem, [
            'key_vocabulary' => [
                ['word' => 'Sustainability', 'definition' => 'keeping ecosystems healthy'],
                ['word' => 'emission', 'definition' => 'something sent out into the air'],
            ],
        ]);

        $this->assertSame(2, $result['imported']);
        $this->assertSame(0, $result['skipped']);
        $this->assertDatabaseHas('vocabularies', [
            'user_id' => $user->id,
            'word' => 'sustainability',
        ]);
        $this->assertDatabaseHas('vocabularies', [
            'user_id' => $user->id,
            'word' => 'emission',
        ]);
    }

    public function test_skips_words_already_in_user_vocabulary(): void
    {
        $user = User::factory()->create();
        Vocabulary::query()->create([
            'user_id' => $user->id,
            'word' => 'climate',
            'meanings' => [['definition' => 'weather patterns']],
        ]);

        $mediaItem = MediaItem::query()->create([
            'user_id' => $user->id,
            'title' => 'Talk',
            'url' => 'https://example.com/video',
            'type' => 'youtube',
            'frequency' => 'weekly',
        ]);

        $dictionary = Mockery::mock(DictionaryService::class);
        $dictionary->shouldReceive('lookup')->never();

        $importer = new MediaKeyVocabularyImporter($dictionary);

        $result = $importer->importFromAnalysis($mediaItem, [
            'key_vocabulary' => [
                ['word' => 'climate', 'definition' => 'duplicate'],
            ],
        ]);

        $this->assertSame(0, $result['imported']);
        $this->assertSame(1, $result['skipped']);
        $this->assertSame(1, Vocabulary::query()->where('user_id', $user->id)->count());
    }

    public function test_uses_dictionary_when_analysis_definition_is_empty(): void
    {
        $user = User::factory()->create();
        $mediaItem = MediaItem::query()->create([
            'user_id' => $user->id,
            'title' => 'Talk',
            'url' => 'https://example.com/video',
            'type' => 'youtube',
            'frequency' => 'weekly',
        ]);

        $dictionary = Mockery::mock(DictionaryService::class);
        $dictionary->shouldReceive('lookup')
            ->once()
            ->with('habitat')
            ->andReturn([
                'word' => 'habitat',
                'phonetic' => '/ˈhæbɪtæt/',
                'meanings' => [
                    ['part_of_speech' => 'noun', 'definition' => 'natural home of an animal'],
                ],
            ]);
        $dictionary->shouldReceive('meaningsForVocabulary')
            ->once()
            ->andReturnUsing(fn (array $meanings) => $meanings);
        $dictionary->shouldReceive('upsertOnSave')->once()->andReturn(null);

        $importer = new MediaKeyVocabularyImporter($dictionary);

        $result = $importer->importFromAnalysis($mediaItem, [
            'key_vocabulary' => [
                ['word' => 'habitat', 'definition' => ''],
            ],
        ]);

        $this->assertSame(1, $result['imported']);
        $this->assertDatabaseHas('vocabularies', [
            'user_id' => $user->id,
            'word' => 'habitat',
            'phonetic' => '/ˈhæbɪtæt/',
        ]);
    }
}
