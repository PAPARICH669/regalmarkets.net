<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MatchingBonusLog;
use App\Models\RoiLog;
use App\Models\SponsorBonusLog;
use App\Services\NetworkService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(protected NetworkService $network) {}

    public function index(Request $request)
    {
        $user = $request->user()->load(['rank', 'wallets', 'packages']);

        $packages      = $user->packages;
        $activePackages = $packages->where('status', 'active');

        $totalReturn  = (float) $packages->sum('total_return');
        $totalPaid    = (float) $packages->sum('total_paid');
        $remainingRoi = max($totalReturn - $totalPaid, 0);

        // Daily ROI = current (admin-set, variable) daily % × active principal.
        $dailyPercent    = (float) app(\App\Services\SettingsService::class)->get('roi_daily_percent');
        $activePrincipal = (float) $activePackages->sum('principal');
        $dailyRoi        = round($dailyPercent / 100 * $activePrincipal, 8);

        $sponsorBonus  = (float) SponsorBonusLog::where('to_user_id', $user->id)->sum('amount');
        $matchingBonus = (float) MatchingBonusLog::where('to_user_id', $user->id)->sum('amount');
        $roiEarned     = (float) RoiLog::where('user_id', $user->id)->sum('amount');

        // Last 14 days earnings series (for chart)
        $invested = (float) $user->total_invested;
        $series = collect(range(13, 0))->map(function ($daysAgo) use ($user, $invested) {
            $date = Carbon::today()->subDays($daysAgo);
            $roi  = (float) RoiLog::where('user_id', $user->id)->whereDate('roi_date', $date)->sum('amount');
            $match = (float) MatchingBonusLog::where('to_user_id', $user->id)->whereDate('created_at', $date)->sum('amount');
            $spon = (float) SponsorBonusLog::where('to_user_id', $user->id)->whereDate('created_at', $date)->sum('amount');
            return [
                'date'        => $date->toDateString(),
                'roi'         => $roi,
                'roi_percent' => $invested > 0 ? round($roi / $invested * 100, 3) : 0,
                'matching'    => $match,
                'sponsor'     => $spon,
                'total'       => $roi + $match + $spon,
            ];
        });

        $stats = $this->network->stats($user);

        return response()->json([
            'rank'           => $user->rankName(),
            'rank_level'     => $user->rank?->level ?? 1,
            'referral_code'  => $user->referral_code,
            'referral_link'  => rtrim(config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:3000')), '/') . '/register?ref=' . $user->referral_code,
            'wallets'        => [
                'A' => (float) $user->walletBalance('A'),
                'E' => (float) $user->walletBalance('E'),
            ],
            'totals'         => [
                'total_invested' => (float) $user->total_invested,
                'total_return'   => $totalReturn,
                'total_paid'     => $totalPaid,
                'remaining_roi'  => $remainingRoi,
                'daily_roi'      => $dailyRoi,
                'roi_earned'     => $roiEarned,
                'sponsor_bonus'  => $sponsorBonus,
                'matching_bonus' => $matchingBonus,
            ],
            'packages'       => $packages->map(fn ($p) => [
                'id'           => $p->id,
                'principal'    => (float) $p->principal,
                'total_return' => (float) $p->total_return,
                'total_paid'   => (float) $p->total_paid,
                'daily_amount' => (float) $p->daily_amount,
                'status'       => $p->status,
                'progress'     => $p->total_return > 0 ? round(($p->total_paid / $p->total_return) * 100, 2) : 0,
                'source'       => $p->source,
                'activated_at' => $p->activated_at,
            ])->values(),
            'team'           => $stats,
            'earnings_series'=> $series,
        ]);
    }
}
