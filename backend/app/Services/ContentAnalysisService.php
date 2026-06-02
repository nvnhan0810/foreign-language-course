<?php

namespace App\Services;

class ContentAnalysisService
{
    public function __construct(private readonly CursorAgentService $cursor) {}

    public function analyze(string $transcript, string $title, string $language = 'en'): array
    {
        if ($this->cursor->isConfigured()) {
            $analysis = $this->analyzeWithCursor($transcript, $title, $language);

            if ($analysis) {
                return $analysis;
            }
        }

        return $this->analyzeLocally($transcript, $title, $language);
    }

    private function analyzeWithCursor(string $transcript, string $title, string $language): ?array
    {
        $excerpt = mb_substr($transcript, 0, 12000);

        $prompt = <<<PROMPT
Analyze this listening content for language learners.

Title: {$title}
Language: {$language}

Transcript:
{$excerpt}

Return JSON with keys:
- summary (string)
- topics (string array)
- key_vocabulary (array of objects with word and definition)
- difficulty (beginner|intermediate|advanced)
- main_ideas (string array)
PROMPT;

        $payload = $this->cursor->completeJson($prompt);

        if (! is_array($payload)) {
            return null;
        }

        return [
            'summary' => $payload['summary'] ?? '',
            'topics' => $payload['topics'] ?? [],
            'key_vocabulary' => $payload['key_vocabulary'] ?? [],
            'difficulty' => $payload['difficulty'] ?? 'intermediate',
            'main_ideas' => $payload['main_ideas'] ?? [],
            'source' => 'cursor',
        ];
    }

    private function analyzeLocally(string $transcript, string $title, string $language): array
    {
        $words = str_word_count(strtolower($transcript), 1);
        $uniqueWords = array_unique($words);
        $wordCount = count($words);

        $sentences = preg_split('/[.!?]+/', $transcript, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $summary = trim($sentences[0] ?? mb_substr($transcript, 0, 200));

        $stopWords = ['the', 'a', 'an', 'is', 'are', 'was', 'were', 'to', 'of', 'and', 'in', 'on', 'for', 'it', 'that', 'this'];
        $freq = array_count_values(array_filter($uniqueWords, fn ($w) => strlen($w) > 4 && ! in_array($w, $stopWords, true)));
        arsort($freq);
        $topWords = array_slice(array_keys($freq), 0, 8);

        return [
            'summary' => $summary,
            'topics' => [$title],
            'key_vocabulary' => array_map(fn ($w) => ['word' => $w, 'definition' => ''], $topWords),
            'difficulty' => $wordCount > 800 ? 'advanced' : ($wordCount > 300 ? 'intermediate' : 'beginner'),
            'main_ideas' => array_slice(array_map('trim', $sentences), 0, 3),
            'word_count' => $wordCount,
            'source' => 'local',
            'language' => $language,
        ];
    }
}
