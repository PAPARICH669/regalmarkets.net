<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MatchingBonusLog;
use App\Models\RoiLog;
use App\Models\SponsorBonusLog;
use App\Services\NetworkService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function __construct(protected NetworkService $network) {}

    /**
     * Daily report for the logged-in member:
     *  - per-day ROI, sponsor bonus, matching bonus (last N days)
     *  - group sales per level + total group sales
     */
    public function daily(Request $request)
    {
        $user = $request->user();
        $days = (int) $request->query('days', 30);
        $days = max(1, min($days, 365));

        $start = Carbon::today()->subDays($days - 1);

        // ---- Per-day aggregates (one grouped query each) ---------------------
        $roi = RoiLog::where('user_id', $user->id)
            ->where('roi_date', '>=', $start->toDateString())
            ->groupBy('roi_date')
            ->get([DB::raw('roi_date as d'), DB::raw('SUM(amount) as total')])
            ->keyBy(fn ($r) => (string) $r->d);

        $sponsor = SponsorBonusLog::where('to_user_id', $user->id)
            ->where('created_at', '>=', $start)
            ->groupBy(DB::raw('DATE(created_at)'))
            ->get([DB::raw('DATE(created_at) as d'), DB::raw('SUM(amount) as total')])
            ->keyBy(fn ($r) => (string) $r->d);

        $matching = MatchingBonusLog::where('to_user_id', $user->id)
            ->where('created_at', '>=', $start)
            ->groupBy(DB::raw('DATE(created_at)'))
            ->get([DB::raw('DATE(created_at) as d'), DB::raw('SUM(amount) as total')])
            ->keyBy(fn ($r) => (string) $r->d);

        // Denominator for the daily ROI rate = the member's invested capital.
        $invested = (float) $user->total_invested;

        $rows = [];
        $sumRoi = $sumSpon = $sumMatch = 0.0;
        for ($i = 0; $i < $days; $i++) {
            $date = $start->copy()->addDays($i)->toDateString();
            $r = (float) ($roi[$date]->total ?? 0);
            $s = (float) ($sponsor[$date]->total ?? 0);
            $m = (float) ($matching[$date]->total ?? 0);
            $sumRoi += $r; $sumSpon += $s; $sumMatch += $m;
            $rows[] = [
                'date'        => $date,
                'roi'         => round($r, 8),
                'roi_percent' => $invested > 0 ? round($r / $invested * 100, 3) : 0,
                'sponsor'     => round($s, 8),
                'matching'    => round($m, 8),
                'total'       => round($r + $s + $m, 8),
            ];
        }

        // Most recent first for display
        $rows = array_reverse($rows);

        $group = $this->network->salesByLevel($user);

        return response()->json([
            'range_days'          => $days,
            'total_invested'      => $invested,
            'daily_roi_rate'      => (float) app(\App\Services\SettingsService::class)->get('roi_daily_percent'),
            'daily'               => $rows,
            'totals'              => [
                'roi'      => round($sumRoi, 8),
                'sponsor'  => round($sumSpon, 8),
                'matching' => round($sumMatch, 8),
                'earnings' => round($sumRoi + $sumSpon + $sumMatch, 8),
            ],
            'group_sales_by_level'=> $group['levels'],
            'total_group_sales'   => round($group['total_sales'], 8),
            'total_group_members' => $group['total_members'],
        ]);
    }
}
