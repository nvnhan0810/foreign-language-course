<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DictionaryService;
use Illuminate\Http\JsonResponse;

class DictionaryController extends Controller
{
    public function __construct(private readonly DictionaryService $dictionary) {}

    public function show(string $word): JsonResponse
    {
        $result = $this->dictionary->lookup($word);

        if (! $result) {
            return response()->json(['message' => 'Không tìm thấy từ.'], 404);
        }

        return response()->json($result);
    }
}
