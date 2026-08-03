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

        // Daily commission = admin-set daily % × ELIGIBLE active principal.
        // Commission starts the day AFTER funding, so packages activated today are
        // excluded — they begin earning (and showing here) tomorrow.
        $today           = Carbon::today()->toDateString();
        $dailyPercent    = (float) app(\App\Services\SettingsService::class)->get('roi_daily_percent');
        $eligiblePrincipal = (float) $activePackages
            ->filter(fn ($p) => $p->activated_at && $p->activated_at->toDateString() < $today)
            ->sum('principal');
        $dailyRoi        = round($dailyPercent / 100 * $eligiblePrincipal, 8);

        $sponsorBonus  = (float) SponsorBonusLog::where('to_user_id', $user->id)->sum('amount');
        $matchingBonus = (float) MatchingBonusLog::where('to_user_id', $user->id)->sum('amount');
        $roiEarned     = (float) RoiLog::where('user_id', $user->id)->sum('amount');

        // Last 14 days earnings series (for chart). Pre-aggregate in 3 grouped
        // queries (was 3 queries × 14 days = 42).
        $invested = (float) $user->total_invested;
        $from     = Carbon::today()->subDays(13)->toDateString();
        $roiByDate = RoiLog::where('user_id', $user->id)->where('roi_date', '>=', $from)
            ->selectRaw("DATE_FORMAT(roi_date,'%Y-%m-%d') d, SUM(amount) s")->groupBy('d')->pluck('s', 'd');
        $matchByDate = MatchingBonusLog::where('to_user_id', $user->id)->where('created_at', '>=', $from . ' 00:00:00')
            ->selectRaw("DATE_FORMAT(created_at,'%Y-%m-%d') d, SUM(amount) s")->groupBy('d')->pluck('s', 'd');
        $sponByDate = SponsorBonusLog::where('to_user_id', $user->id)->where('created_at', '>=', $from . ' 00:00:00')
            ->selectRaw("DATE_FORMAT(created_at,'%Y-%m-%d') d, SUM(amount) s")->groupBy('d')->pluck('s', 'd');

        $series = collect(range(13, 0))->map(function ($daysAgo) use ($invested, $roiByDate, $matchByDate, $sponByDate) {
            $ds    = Carbon::today()->subDays($daysAgo)->toDateString();
            $roi   = (float) ($roiByDate[$ds] ?? 0);
            $match = (float) ($matchByDate[$ds] ?? 0);
            $spon  = (float) ($sponByDate[$ds] ?? 0);
            return [
                'date'        => $ds,
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
            'referral_link'  => rtrim(config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:3000')), '/') . '/register?ref=' . $user->username,
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
