<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Gate a member-area route on a per-user permission flag (e.g. can_kyc, can_cr).
 * Admins always pass. Usage: ->middleware('perm:can_kyc')
 */
class CheckPermission
{
    public function handle(Request $request, Closure $next, string $perm)
    {
        $user = $request->user();
        if (! $user || (! $user->is_admin && ! (bool) $user->{$perm})) {
            return response()->json(['message' => 'Forbidden. You are not assigned to this task.'], 403);
        }
        return $next($request);
    }
}
