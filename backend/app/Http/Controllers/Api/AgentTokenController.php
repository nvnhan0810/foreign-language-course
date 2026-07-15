<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\AgentToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

class AgentTokenController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $tokens = $request->user()
            ->tokens()
            ->where('name', AgentToken::NAME)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (PersonalAccessToken $token) => $this->serialize($token));

        return response()->json(['data' => $tokens]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:60'],
            'abilities' => ['nullable', 'array'],
            'abilities.*' => ['string', 'in:'.implode(',', AgentToken::allowedAbilities())],
        ]);

        $abilities = $data['abilities'] ?? AgentToken::defaultAbilities();
        if ($abilities === []) {
            $abilities = AgentToken::defaultAbilities();
        }

        $newToken = $request->user()->createToken(
            AgentToken::NAME,
            array_values(array_unique($abilities)),
        );

        /** @var PersonalAccessToken $accessToken */
        $accessToken = $newToken->accessToken;

        return response()->json([
            'data' => $this->serialize($accessToken),
            'token' => $newToken->plainTextToken,
        ], 201);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $token = $request->user()
            ->tokens()
            ->where('name', AgentToken::NAME)
            ->where('id', $id)
            ->first();

        if ($token === null) {
            abort(404, 'Agent token not found.');
        }

        $token->delete();

        return response()->json(['message' => 'Agent API key revoked.']);
    }

    /** @return array<string, mixed> */
    private function serialize(PersonalAccessToken $token): array
    {
        return [
            'id' => $token->id,
            'name' => $token->name,
            'abilities' => $token->abilities ?? [],
            'last_used_at' => $token->last_used_at?->toIso8601String(),
            'created_at' => $token->created_at?->toIso8601String(),
        ];
    }
}
