<?php

namespace App\Http\Middleware;

use App\Services\MaintenanceService;
use Closure;
use Illuminate\Http\Request;

/**
 * Blocks sensitive actions (login, withdraw, transfer) during the daily
 * maintenance window. Admins bypass the block.
 */
class MaintenanceWindow
{
    public function __construct(protected MaintenanceService $maintenance) {}

    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if ($user && $user->is_admin) {
            return $next($request);
        }

        if ($this->maintenance->isActive()) {
            return response()->json([
                'message'     => 'System is under maintenance. Please try again after the window ends.',
                'maintenance' => $this->maintenance->status(),
            ], 503);
        }

        return $next($request);
    }
}
