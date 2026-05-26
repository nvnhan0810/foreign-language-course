<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AppSettingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SyncController extends Controller
{
    public function index(Request $request, AppSettingService $settings): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'vocabularies' => $user->vocabularies()->with('examples')->orderByDesc('created_at')->get(),
            'media_items' => $user->mediaItems()->orderBy('next_listen_at')->get(),
            'app_name' => $settings->get('app_name', 'FLC'),
            'extension_notice' => $settings->get('extension_notice', ''),
            'synced_at' => now()->toIso8601String(),
        ]);
    }
}
