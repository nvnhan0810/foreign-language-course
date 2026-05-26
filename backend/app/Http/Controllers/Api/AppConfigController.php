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
        ]);
    }
}
