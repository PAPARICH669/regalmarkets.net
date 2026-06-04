<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Blocks sensitive money actions (deposit / withdraw) until the member's KYC is
 * verified by an admin. Admins bypass.
 */
class EnsureKycVerified
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if ($user && ! $user->is_admin && ($user->kyc_status ?? 'unsubmitted') !== 'verified') {
            return response()->json([
                'message'      => 'Please complete KYC verification before you can deposit or withdraw.',
                'kyc_required' => true,
                'kyc_status'   => $user->kyc_status ?? 'unsubmitted',
            ], 423);
        }
        return $next($request);
    }
}
