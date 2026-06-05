<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Allows admin-panel access to full admins AND limited staff
 * (staff can only reach the KYC + edit-profile routes).
 */
class EnsureStaffOrAdmin
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if (! $user || (! $user->is_admin && ! $user->is_staff)) {
            return response()->json(['message' => 'Forbidden. Staff or admin access required.'], 403);
        }
        return $next($request);
    }
}
