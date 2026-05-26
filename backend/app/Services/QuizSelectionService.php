<?php

namespace App\Services;

use App\Models\User;
use App\Models\Vocabulary;
use Illuminate\Support\Collection;

class QuizSelectionService
{
    /**
     * @return array<string, mixed>|null
     */
    public function nextQuestion(User $user, int $count = 1): ?array
    {
        $vocabularies = $user->vocabularies()->get();

        if ($vocabularies->count() < 4) {
            return null;
        }

        $target = $this->pickWeighted($vocabularies);

        if (! $target) {
            return null;
        }

        $questionType = random_int(0, 1) === 0 ? 'definition_to_word' : 'word_to_definition';

        $correctDefinition = $this->primaryDefinition($target);

        if ($questionType === 'definition_to_word') {
            $options = $this->buildWordOptions($vocabularies, $target);
            $prompt = $correctDefinition;
            $correctAnswer = $target->word;
        } else {
            $options = $this->buildDefinitionOptions($vocabularies, $target);
            $prompt = $target->word;
            $correctAnswer = $correctDefinition;
        }

        shuffle($options);

        return [
            'vocabulary_id' => $target->id,
            'question_type' => $questionType,
            'prompt' => $prompt,
            'options' => $options,
            'correct_answer' => $correctAnswer,
        ];
    }

    /**
     * @param  Collection<int, Vocabulary>  $vocabularies
     */
    private function pickWeighted(Collection $vocabularies): ?Vocabulary
    {
        $weights = $vocabularies->map(function (Vocabulary $v) {
            $base = 1 / ($v->times_quizzed + 1);
            $decay = 1.0;

            if ($v->last_quizzed_at) {
                $hours = now()->diffInHours($v->last_quizzed_at);
                $decay = min(1.0, max(0.15, $hours / 24));
            }

            return max(0.01, $base * $decay);
        });

        $total = $weights->sum();
        $roll = mt_rand() / mt_getrandmax() * $total;
        $cumulative = 0;

        foreach ($vocabularies as $index => $vocabulary) {
            $cumulative += $weights[$index];
            if ($roll <= $cumulative) {
                return $vocabulary;
            }
        }

        return $vocabularies->first();
    }

    private function primaryDefinition(Vocabulary $vocabulary): string
    {
        $meanings = $vocabulary->meanings ?? [];

        return $meanings[0]['definition'] ?? $vocabulary->word;
    }

    /**
     * @param  Collection<int, Vocabulary>  $vocabularies
     * @return list<string>
     */
    private function buildWordOptions(Collection $vocabularies, Vocabulary $target): array
    {
        $distractors = $vocabularies
            ->where('id', '!=', $target->id)
            ->shuffle()
            ->take(3)
            ->pluck('word')
            ->all();

        return array_values(array_unique([$target->word, ...$distractors]));
    }

    /**
     * @param  Collection<int, Vocabulary>  $vocabularies
     * @return list<string>
     */
    private function buildDefinitionOptions(Collection $vocabularies, Vocabulary $target): array
    {
        $correct = $this->primaryDefinition($target);
        $distractors = $vocabularies
            ->where('id', '!=', $target->id)
            ->shuffle()
            ->take(3)
            ->map(fn (Vocabulary $v) => $this->primaryDefinition($v))
            ->all();

        return array_values(array_unique([$correct, ...$distractors]));
    }
}
