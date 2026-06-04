<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AppSettingService;
use App\Services\VocabQuizReminderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationSettingsController extends Controller
{
    public function show(Request $request, AppSettingService $settings): JsonResponse
    {
        $preference = VocabQuizReminderService::ensurePreference($request->user());

        return response()->json([
            'vocab_quiz_push_enabled' => $preference->vocab_quiz_push_enabled,
            'global_vocab_quiz_push_enabled' => $settings->getBool('vocab_quiz_push_enabled', true),
            'reminder_schedule' => [
                'timezone' => 'Asia/Ho_Chi_Minh',
                'midday' => '11:00',
                'evening' => '20:00',
            ],
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'vocab_quiz_push_enabled' => ['required', 'boolean'],
        ]);

        $preference = VocabQuizReminderService::ensurePreference($request->user());
        $preference->vocab_quiz_push_enabled = $data['vocab_quiz_push_enabled'];
        $preference->save();

        return response()->json([
            'vocab_quiz_push_enabled' => $preference->vocab_quiz_push_enabled,
        ]);
    }
}
