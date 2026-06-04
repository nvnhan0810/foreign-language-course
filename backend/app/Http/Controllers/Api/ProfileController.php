<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ListeningAttempt;
use App\Models\QuizAttempt;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class ProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        $vocabularyCount = $user->vocabularies()->count();
        $mediaCount = $user->mediaItems()->count();

        $listeningAvg = ListeningAttempt::query()
            ->where('user_id', $user->id)
            ->avg('percentage');

        $vocabStats = QuizAttempt::query()
            ->where('user_id', $user->id)
            ->selectRaw('SUM(CASE WHEN correct = 1 THEN 1 ELSE 0 END) as correct_count')
            ->selectRaw('COUNT(*) as total_count')
            ->first();

        $vocabCorrect = (int) ($vocabStats->correct_count ?? 0);
        $vocabTotal = (int) ($vocabStats->total_count ?? 0);
        $vocabPercent = $vocabTotal > 0 ? round(($vocabCorrect / $vocabTotal) * 100, 1) : null;

        $averageScorePercent = $this->averageScorePercent(
            $listeningAvg !== null ? (float) $listeningAvg : null,
            $vocabPercent
        );

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'stats' => [
                'vocabulary_count' => $vocabularyCount,
                'media_count' => $mediaCount,
                'average_score_percent' => $averageScorePercent,
            ],
            'history' => $this->buildHistory($user->id),
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildHistory(int $userId): array
    {
        $listening = ListeningAttempt::query()
            ->where('user_id', $userId)
            ->with(['assessment:id,title,type'])
            ->orderByDesc('completed_at')
            ->limit(30)
            ->get();

        $vocabSessions = QuizAttempt::query()
            ->where('user_id', $userId)
            ->selectRaw('DATE(created_at) as session_date')
            ->selectRaw('SUM(CASE WHEN correct = 1 THEN 1 ELSE 0 END) as score')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('MAX(created_at) as completed_at')
            ->groupByRaw('DATE(created_at)')
            ->orderByDesc('session_date')
            ->limit(30)
            ->get();

        $items = new Collection;

        foreach ($listening as $attempt) {
            $assessment = $attempt->assessment;
            if (! $assessment) {
                continue;
            }

            $items->push([
                'id' => 'listening-'.$attempt->id,
                'kind' => 'listening',
                'title' => $assessment->title,
                'type' => $assessment->type,
                'score' => $attempt->score,
                'total' => $attempt->total,
                'completed_at' => $attempt->completed_at?->toIso8601String(),
            ]);
        }

        foreach ($vocabSessions as $session) {
            $date = Carbon::parse($session->session_date);
            $items->push([
                'id' => 'vocab-'.$session->session_date,
                'kind' => 'vocab',
                'title' => 'Vocabulary Quiz — '.$date->format('d/m/Y'),
                'type' => 'quiz',
                'score' => (int) $session->score,
                'total' => (int) $session->total,
                'completed_at' => Carbon::parse($session->completed_at)->toIso8601String(),
            ]);
        }

        return $items
            ->sortByDesc(fn (array $item) => $item['completed_at'] ?? '')
            ->take(30)
            ->values()
            ->all();
    }

    private function averageScorePercent(?float $listeningAvg, ?float $vocabPercent): ?float
    {
        $scores = array_filter(
            [$listeningAvg, $vocabPercent],
            fn ($v) => $v !== null
        );

        if ($scores === []) {
            return null;
        }

        return round(array_sum($scores) / count($scores), 1);
    }
}
