<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AppSettingService;
use Illuminate\Http\JsonResponse;

class AppConfigController extends Controller
{
    public function __invoke(AppSettingService $settings): JsonResponse
    {
        return response()->json([
            'app_name' => $settings->get('app_name', 'FLC'),
            'extension_notice' => $settings->get('extension_notice', ''),
            'vocab_quiz_push_enabled' => $settings->getBool('vocab_quiz_push_enabled', true),
            'vocab_quiz_reminder_schedule' => [
                'timezone' => 'Asia/Ho_Chi_Minh',
                'midday' => '11:00',
                'evening' => '20:00',
            ],
        ]);
    }
}
