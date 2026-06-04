<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DevicePushToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();
        DevicePushToken::query()->where('user_id', $user->id)->delete();
        $user->currentAccessToken()?->delete();

        return response()->json(['message' => 'Đã đăng xuất.']);
    }

    public function user(Request $request): JsonResponse
    {
        return response()->json(['user' => $request->user()]);
    }
}
