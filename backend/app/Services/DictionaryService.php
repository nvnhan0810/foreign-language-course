<?php

namespace App\Services;

use App\Models\DictionaryCache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class DictionaryService
{
    public function lookup(string $word): ?array
    {
        $normalized = Str::lower(trim($word));

        if ($normalized === '') {
            return null;
        }

        $cached = DictionaryCache::query()->find($normalized);

        if ($cached && $cached->cached_at->gt(now()->subDays(7))) {
            return $cached->payload;
        }

        $response = Http::timeout(10)->get(
            'https://api.dictionaryapi.dev/api/v2/entries/en/'.$normalized
        );

        if (! $response->successful()) {
            return null;
        }

        $entries = $response->json();

        if (! is_array($entries) || $entries === []) {
            return null;
        }

        $payload = $this->normalizeEntries($normalized, $entries);

        DictionaryCache::query()->updateOrCreate(
            ['word' => $normalized],
            ['payload' => $payload, 'cached_at' => now()]
        );

        return $payload;
    }

    /**
     * @param  array<int, mixed>  $entries
     */
    private function normalizeEntries(string $word, array $entries): array
    {
        $entry = $entries[0];
        $meanings = [];

        foreach ($entry['meanings'] ?? [] as $meaning) {
            $partOfSpeech = $meaning['partOfSpeech'] ?? null;
            foreach ($meaning['definitions'] ?? [] as $definition) {
                $meanings[] = [
                    'part_of_speech' => $partOfSpeech,
                    'definition' => $definition['definition'] ?? '',
                    'example' => $definition['example'] ?? null,
                ];
            }
        }

        $phonetic = $entry['phonetic'] ?? null;
        $audioUrl = null;
        if (! empty($entry['phonetics'])) {
            foreach ($entry['phonetics'] as $p) {
                if (! empty($p['text']) && $phonetic === null) {
                    $phonetic = $p['text'];
                }
            }
            $audioUrl = $this->extractAudioUrl($entry['phonetics']);
        }

        return [
            'word' => $word,
            'phonetic' => $phonetic,
            'audio_url' => $audioUrl,
            'meanings' => array_slice($meanings, 0, 12),
            'source' => 'dictionaryapi.dev',
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $phonetics
     */
    private function extractAudioUrl(array $phonetics): ?string
    {
        $candidates = [];

        foreach ($phonetics as $phonetic) {
            $audio = $phonetic['audio'] ?? null;

            if (! is_string($audio) || $audio === '') {
                continue;
            }

            $candidates[] = $audio;
        }

        if ($candidates === []) {
            return null;
        }

        foreach ($candidates as $audio) {
            if (str_contains($audio, '-us.') || str_contains($audio, '/us-')) {
                return $audio;
            }
        }

        return $candidates[0];
    }
}
