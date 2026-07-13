<?php

namespace App\Services;

use App\Models\MediaItem;
use App\Models\Vocabulary;
use App\Models\VocabularyExample;
use Illuminate\Support\Str;

class MediaKeyVocabularyImporter
{
    private const MAX_WORDS = 15;

    public function __construct(private readonly DictionaryService $dictionary) {}

    /**
     * @param  array<string, mixed>  $analysis
     * @return array{imported: int, skipped: int, words: list<string>}
     */
    public function importFromAnalysis(MediaItem $mediaItem, array $analysis): array
    {
        $user = $mediaItem->user;

        if (! $user) {
            return ['imported' => 0, 'skipped' => 0, 'words' => []];
        }

        $entries = $analysis['key_vocabulary'] ?? [];

        if (! is_array($entries) || $entries === []) {
            return ['imported' => 0, 'skipped' => 0, 'words' => []];
        }

        $imported = 0;
        $skipped = 0;
        $words = [];

        foreach (array_slice($entries, 0, self::MAX_WORDS) as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $word = Str::lower(trim((string) ($entry['word'] ?? '')));

            if ($word === '' || ! preg_match('/^[a-z][a-z\'-]*$/', $word)) {
                continue;
            }

            if ($user->vocabularies()->where('word', $word)->exists()) {
                $skipped++;

                continue;
            }

            $lookup = $this->dictionary->lookup($word);
            $meanings = $this->buildMeanings($entry, $lookup);

            if ($meanings === []) {
                continue;
            }

            $vocabMeanings = $this->dictionary->meaningsForVocabulary($meanings);

            $vocabulary = $user->vocabularies()->create([
                'word' => $word,
                'phonetic' => $lookup['phonetic'] ?? null,
                'meanings' => $vocabMeanings,
            ]);

            foreach (array_slice($vocabMeanings, 0, 5) as $meaning) {
                if (! empty($meaning['example'])) {
                    VocabularyExample::query()->create([
                        'vocabulary_id' => $vocabulary->id,
                        'example' => $meaning['example'],
                        'definition_ref' => $meaning['definition'] ?? null,
                    ]);
                }
            }

            $this->dictionary->upsertOnSave($word, $lookup ?? [
                'word' => $word,
                'phonetic' => null,
                'audio_url' => null,
                'meanings' => $meanings,
                'synonyms' => [],
                'antonyms' => [],
                'source' => 'user_save',
            ]);

            $imported++;
            $words[] = $word;
        }

        return [
            'imported' => $imported,
            'skipped' => $skipped,
            'words' => $words,
        ];
    }

    /**
     * @param  array<string, mixed>  $entry
     * @param  array<string, mixed>|null  $lookup
     * @return list<array<string, mixed>>
     */
    private function buildMeanings(array $entry, ?array $lookup): array
    {
        $definition = trim((string) ($entry['definition'] ?? ''));

        if ($definition !== '') {
            return [[
                'part_of_speech' => $entry['part_of_speech'] ?? null,
                'definition' => $definition,
                'example' => $entry['example'] ?? null,
            ]];
        }

        $fromDictionary = $lookup['meanings'] ?? [];

        return is_array($fromDictionary) ? array_values($fromDictionary) : [];
    }
}
