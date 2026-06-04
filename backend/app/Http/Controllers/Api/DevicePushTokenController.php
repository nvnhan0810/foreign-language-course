<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DevicePushToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DevicePushTokenController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'max:512'],
            'platform' => ['required', 'in:ios,android'],
        ]);

        DevicePushToken::query()->updateOrCreate(
            [
                'user_id' => $request->user()->id,
                'token' => $data['token'],
            ],
            ['platform' => $data['platform']],
        );

        return response()->json(['message' => 'Đã lưu token push.']);
    }

    public function destroy(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'max:512'],
        ]);

        DevicePushToken::query()
            ->where('user_id', $request->user()->id)
            ->where('token', $data['token'])
            ->delete();

        return response()->json(['message' => 'Đã xóa token push.']);
    }
}
