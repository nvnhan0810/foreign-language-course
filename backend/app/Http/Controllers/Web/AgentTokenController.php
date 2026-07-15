<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Support\AgentToken;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

class AgentTokenController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $newToken = $request->user()->createToken(
            AgentToken::NAME,
            AgentToken::defaultAbilities(),
        );

        return redirect()
            ->route('user.home.profile')
            ->with('success', 'Agent API key created. Copy it now — it will not be shown again.')
            ->with('agent_api_token', $newToken->plainTextToken);
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        /** @var PersonalAccessToken|null $token */
        $token = $request->user()
            ->tokens()
            ->where('name', AgentToken::NAME)
            ->where('id', $id)
            ->first();

        if ($token === null) {
            return redirect()
                ->route('user.home.profile')
                ->with('error', 'Agent API key not found.');
        }

        $token->delete();

        return redirect()
            ->route('user.home.profile')
            ->with('success', 'Agent API key revoked.');
    }
}
