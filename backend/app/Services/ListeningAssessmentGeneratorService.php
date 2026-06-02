<?php

namespace App\Services;

use App\Models\ListeningAssessment;
use App\Models\ListeningQuestion;
use App\Models\MediaItem;
use Illuminate\Support\Facades\DB;

class ListeningAssessmentGeneratorService
{
    public function __construct(private readonly CursorAgentService $cursor) {}

    /**
     * @return array<string, ListeningAssessment>
     */
    public function generateAll(MediaItem $mediaItem): array
    {
        $this->getSourceContent($mediaItem);

        $results = [];

        foreach ([ListeningAssessment::TYPE_QUIZ, ListeningAssessment::TYPE_TEST, ListeningAssessment::TYPE_EXAM] as $type) {
            $results[$type] = $this->generate($mediaItem, $type);
        }

        return $results;
    }

    public function generate(MediaItem $mediaItem, string $type): ListeningAssessment
    {
        $config = config("listening.assessments.{$type}");

        if (! is_array($config)) {
            throw new \InvalidArgumentException("Unknown assessment type: {$type}");
        }

        $questionCount = (int) $config['question_count'];

        return DB::transaction(function () use ($mediaItem, $type, $config, $questionCount) {
            $assessment = ListeningAssessment::query()->updateOrCreate(
                [
                    'media_item_id' => $mediaItem->id,
                    'type' => $type,
                ],
                [
                    'user_id' => $mediaItem->user_id,
                    'title' => "{$mediaItem->title} — {$config['title_suffix']}",
                    'description' => "Listening {$type} based on: {$mediaItem->title}",
                    'question_count' => $questionCount,
                    'time_limit_minutes' => $config['time_limit_minutes'],
                    'status' => ListeningAssessment::STATUS_GENERATING,
                    'generated_at' => null,
                ]
            );

            $assessment->questions()->delete();

            $questions = $this->buildQuestions(
                $this->getSourceContent($mediaItem),
                $mediaItem->title,
                $type,
                $questionCount,
                $mediaItem->analysis_payload ?? []
            );

            foreach ($questions as $index => $question) {
                ListeningQuestion::query()->create([
                    'listening_assessment_id' => $assessment->id,
                    'order' => $index + 1,
                    ...$question,
                ]);
            }

            $assessment->update([
                'question_count' => count($questions),
                'status' => ListeningAssessment::STATUS_READY,
                'generated_at' => now(),
            ]);

            return $assessment->fresh(['questions']);
        });
    }

    /**
     * @param  array<string, mixed>  $analysis
     * @return array<int, array<string, mixed>>
     */
    private function buildQuestions(
        string $content,
        string $title,
        string $type,
        int $questionCount,
        array $analysis
    ): array {
        if ($this->cursor->isConfigured()) {
            $questions = $this->generateWithCursor($content, $title, $type, $questionCount, $analysis);

            if ($questions !== []) {
                return $questions;
            }
        }

        return $this->generateLocally($content, $title, $type, $questionCount, $analysis);
    }

    private function getSourceContent(MediaItem $mediaItem): string
    {
        $fromPayload = $mediaItem->analysis_payload['source_content'] ?? null;

        if (is_string($fromPayload) && trim($fromPayload) !== '') {
            return $fromPayload;
        }

        if ($mediaItem->transcript) {
            return $mediaItem->transcript;
        }

        throw new \RuntimeException('Media item has no analyzed content. Run analysis first.');
    }

    /**
     * @param  array<string, mixed>  $analysis
     * @return array<int, array<string, mixed>>
     */
    private function generateWithCursor(
        string $content,
        string $title,
        string $type,
        int $questionCount,
        array $analysis
    ): array {
        $excerpt = mb_substr($content, 0, 12000);
        $analysisJson = json_encode($analysis);
        $contentSource = $analysis['content_source'] ?? MediaContentResolverService::SOURCE_TRANSCRIPT;
        $sourceNote = match ($contentSource) {
            MediaContentResolverService::SOURCE_METADATA => 'No transcript available — infer listening questions from title/description and likely video topics.',
            MediaContentResolverService::SOURCE_NOTES, MediaContentResolverService::SOURCE_TITLE => 'Limited metadata only — create reasonable inference questions about the topic.',
            default => 'Use the transcript for accurate detail questions.',
        };

        $typeGuide = match ($type) {
            ListeningAssessment::TYPE_QUIZ => 'Quick quiz: vocabulary and main idea questions.',
            ListeningAssessment::TYPE_TEST => 'Standard test: mix of detail, inference, and vocabulary.',
            ListeningAssessment::TYPE_EXAM => 'Comprehensive exam: harder inference, detail, and summary questions.',
        };

        $prompt = <<<PROMPT
Generate {$questionCount} listening comprehension questions for language learners.

Title: {$title}
Assessment type: {$type}
Guide: {$typeGuide}
Content source note: {$sourceNote}
Analysis: {$analysisJson}

Content:
{$excerpt}

Return JSON:
{
  "questions": [
    {
      "question_type": "mcq|fill_blank|true_false|comprehension",
      "prompt": "...",
      "options": ["A","B","C","D"] or null,
      "correct_answer": "...",
      "explanation": "...",
      "audio_start_seconds": null,
      "audio_end_seconds": null
    }
  ]
}

Rules:
- Use mcq with exactly 4 options when question_type is mcq
- correct_answer must match one option exactly for mcq
PROMPT;

        $payload = $this->cursor->completeJson($prompt);
        $rawQuestions = $payload['questions'] ?? [];

        if (! is_array($rawQuestions)) {
            return [];
        }

        return $this->normalizeQuestions(array_slice($rawQuestions, 0, $questionCount));
    }

    /**
     * @param  array<string, mixed>  $analysis
     * @return array<int, array<string, mixed>>
     */
    private function generateLocally(
        string $transcript,
        string $title,
        string $type,
        int $questionCount,
        array $analysis
    ): array {
        $sentences = array_values(array_filter(
            array_map('trim', preg_split('/[.!?]+/', $transcript, -1, PREG_SPLIT_NO_EMPTY) ?: []),
            fn ($s) => mb_strlen($s) > 20
        ));

        if ($sentences === []) {
            $sentences = [mb_substr($transcript, 0, 200)];
        }

        $questions = [];
        $vocab = $analysis['key_vocabulary'] ?? [];

        for ($i = 0; $i < $questionCount; $i++) {
            $sentence = $sentences[$i % count($sentences)];

            if ($i === 0) {
                $questions[] = [
                    'question_type' => ListeningQuestion::TYPE_COMPREHENSION,
                    'prompt' => 'What is the main topic of this listening passage?',
                    'options' => [$title, 'Sports news', 'Weather forecast', 'Cooking tutorial'],
                    'correct_answer' => $title,
                    'explanation' => 'The title reflects the main topic.',
                    'audio_start_seconds' => null,
                    'audio_end_seconds' => null,
                ];

                continue;
            }

            if ($i % 3 === 1 && isset($vocab[$i % max(1, count($vocab))])) {
                $word = $vocab[$i % count($vocab)]['word'] ?? 'keyword';
                $questions[] = [
                    'question_type' => ListeningQuestion::TYPE_MCQ,
                    'prompt' => "Which word appears in the listening related to \"{$word}\"?",
                    'options' => [$word, 'random', 'unknown', 'missing'],
                    'correct_answer' => $word,
                    'explanation' => "The word \"{$word}\" is used in the passage.",
                    'audio_start_seconds' => null,
                    'audio_end_seconds' => null,
                ];

                continue;
            }

            if ($i % 3 === 2) {
                $questions[] = [
                    'question_type' => ListeningQuestion::TYPE_TRUE_FALSE,
                    'prompt' => 'True or false: The passage includes the following statement: "'.mb_substr($sentence, 0, 80).'..."',
                    'options' => ['True', 'False'],
                    'correct_answer' => 'True',
                    'explanation' => 'This statement comes directly from the transcript.',
                    'audio_start_seconds' => null,
                    'audio_end_seconds' => null,
                ];

                continue;
            }

            $questions[] = [
                'question_type' => ListeningQuestion::TYPE_FILL_BLANK,
                'prompt' => 'Fill in the blank: "'.preg_replace('/\b(\w{4,})\b/', '______', $sentence, 1).'"',
                'options' => null,
                'correct_answer' => strtok($sentence, ' ') ?: 'answer',
                'explanation' => 'Answer based on the transcript.',
                'audio_start_seconds' => null,
                'audio_end_seconds' => null,
            ];
        }

        return $questions;
    }

    /**
     * @param  array<int, mixed>  $rawQuestions
     * @return array<int, array<string, mixed>>
     */
    private function normalizeQuestions(array $rawQuestions): array
    {
        $normalized = [];

        foreach ($rawQuestions as $raw) {
            if (! is_array($raw) || empty($raw['prompt']) || empty($raw['correct_answer'])) {
                continue;
            }

            $type = $raw['question_type'] ?? ListeningQuestion::TYPE_MCQ;
            $options = $raw['options'] ?? null;

            if ($type === ListeningQuestion::TYPE_MCQ && is_array($options) && count($options) < 4) {
                continue;
            }

            $normalized[] = [
                'question_type' => $type,
                'prompt' => $raw['prompt'],
                'options' => $options,
                'correct_answer' => $raw['correct_answer'],
                'explanation' => $raw['explanation'] ?? null,
                'audio_start_seconds' => $raw['audio_start_seconds'] ?? null,
                'audio_end_seconds' => $raw['audio_end_seconds'] ?? null,
            ];
        }

        return $normalized;
    }
}
