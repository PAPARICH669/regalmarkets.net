<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Server-side idle session timeout: expire (delete) a Sanctum token once it has
 * gone unused for more than IDLE_MINUTES. This complements the frontend's 10-min
 * idle auto-logout and enforces it even when the browser tab was closed (so a
 * stale token can never be reused after a long gap).
 *
 * Runs BEFORE `auth:sanctum` (registered in the `api` middleware group) so we
 * read the token's PREVIOUS `last_used_at` before Sanctum refreshes it.
 */
class EnforceIdleTimeout
{
    /** Inactivity window in minutes — matches the frontend IdleLogout timeout. */
    protected const IDLE_MINUTES = 10;

    public function handle(Request $request, Closure $next)
    {
        $bearer = $request->bearerToken();
        if ($bearer) {
            $token = PersonalAccessToken::findToken($bearer);
            if ($token
                && $token->last_used_at
                && $token->last_used_at->lt(now()->subMinutes(self::IDLE_MINUTES))) {
                $token->delete();
                return response()->json(
                    ['message' => 'Session expired due to inactivity. Please log in again.'],
                    401
                );
            }
        }

        return $next($request);
    }
}
