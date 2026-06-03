<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureNotFrozen
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->user() && $request->user()->is_frozen) {
            return response()->json([
                'message' => 'Your account is frozen. Please contact support.',
                'frozen'  => true,
            ], 423);
        }
        return $next($request);
    }
}
