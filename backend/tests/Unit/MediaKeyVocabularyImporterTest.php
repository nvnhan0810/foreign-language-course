<?php

namespace Tests\Unit;

use App\Models\DictionaryEntry;
use App\Models\DictionaryMeaning;
use App\Models\MediaItem;
use App\Models\User;
use App\Models\Vocabulary;
use Flc\Media\Application\MediaKeyVocabularyImporter;
use Flc\Shared\Application\CommandBus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MediaKeyVocabularyImporterTest extends TestCase
{
    use RefreshDatabase;

    public function test_imports_key_vocabulary_for_media_owner(): void
    {
        $user = User::factory()->create();
        MediaItem::query()->create([
            'user_id' => $user->id,
            'title' => 'Climate change talk',
            'url' => 'https://example.com/video',
            'type' => 'youtube',
            'frequency' => 'weekly',
        ]);

        $importer = new MediaKeyVocabularyImporter(app(CommandBus::class));

        $result = $importer->importFromAnalysis($user->id, [
            'key_vocabulary' => [
                ['word' => 'Sustainability', 'definition' => 'keeping ecosystems healthy'],
                ['word' => 'emission', 'definition' => 'something sent out into the air'],
            ],
        ]);

        $this->assertSame(2, $result['imported']);
        $this->assertSame(0, $result['skipped']);
        $this->assertTrue(
            Vocabulary::query()
                ->where('user_id', $user->id)
                ->whereHas('dictionaryEntry', fn ($q) => $q->where('word', 'sustainability'))
                ->exists()
        );
        $this->assertTrue(
            Vocabulary::query()
                ->where('user_id', $user->id)
                ->whereHas('dictionaryEntry', fn ($q) => $q->where('word', 'emission'))
                ->exists()
        );
        $this->assertDatabaseHas('dictionary_entries', ['word' => 'sustainability']);
        $this->assertDatabaseHas('dictionary_entries', ['word' => 'emission']);
    }

    public function test_skips_words_already_in_user_vocabulary(): void
    {
        $user = User::factory()->create();
        $entry = DictionaryEntry::query()->create([
            'word' => 'climate',
            'source' => 'user_save',
            'is_curated' => false,
            'save_count' => 1,
        ]);
        DictionaryMeaning::query()->create([
            'dictionary_entry_id' => $entry->id,
            'definition' => 'weather patterns',
            'position' => 0,
        ]);
        Vocabulary::query()->create([
            'user_id' => $user->id,
            'dictionary_entry_id' => $entry->id,
        ]);

        $importer = new MediaKeyVocabularyImporter(app(CommandBus::class));

        $result = $importer->importFromAnalysis($user->id, [
            'key_vocabulary' => [
                ['word' => 'climate', 'definition' => 'duplicate'],
            ],
        ]);

        $this->assertSame(0, $result['imported']);
        $this->assertSame(1, $result['skipped']);
        $this->assertSame(1, Vocabulary::query()->where('user_id', $user->id)->count());
    }

    public function test_imports_when_analysis_definition_is_empty(): void
    {
        $user = User::factory()->create();

        $importer = new MediaKeyVocabularyImporter(app(CommandBus::class));

        $result = $importer->importFromAnalysis($user->id, [
            'key_vocabulary' => [
                ['word' => 'habitat', 'definition' => ''],
            ],
        ]);

        $this->assertSame(1, $result['imported']);
        $this->assertTrue(
            Vocabulary::query()
                ->where('user_id', $user->id)
                ->whereHas('dictionaryEntry', fn ($q) => $q->where('word', 'habitat'))
                ->exists()
        );
    }
}
