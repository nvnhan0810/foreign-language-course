<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Agent API keys may only be listed/created/revoked via session (web)
 * or a full app OAuth token (*), never via a scoped agent token.
 */
final class EnsureCanManageAgentTokens
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user === null) {
            abort(401);
        }

        // Session / cookie auth (no access token).
        if ($user->currentAccessToken() === null) {
            return $next($request);
        }

        // Full app tokens (Google OAuth / extension) have ability "*".
        if ($user->tokenCan('*')) {
            return $next($request);
        }

        abort(403, 'Agent API keys can only be managed from the web or mobile app.');
    }
}
