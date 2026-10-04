<?php

namespace App\Services;

use App\Models\RoiDailyRate;
use App\Models\RoiLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Monthly-target ROI schedule generator.
 *
 * In monthly_target mode the admin sets a MONTHLY profit target (e.g. 12%) and a
 * daily range [min, max] (e.g. 0.30%–0.60%). This service pre-generates a daily
 * ROI % for every date in the month such that:
 *
 *   • each day fluctuates up/down within [min, max] (not a monotonic ramp),
 *   • every daily % is a clean 2-decimal number (0.39%, 0.45% — never 0.392%),
 *   • the month's daily %s sum EXACTLY to the target,
 *   • the actual number of days in the month (28/30/31) is handled automatically,
 *   • days already PAID this month are preserved (locked) and the remaining
 *     target is spread only over the days still to come — so switching mid-month
 *     deducts what was already paid at the old flat rate.
 *
 * The schedule is GLOBAL (same daily % for everyone that day). Mid-month joiners
 * naturally get only the days from their start date onward (the ROI engine skips
 * packages until the day after they fund), so they receive a pro-rata slice of the
 * target, not the full month.
 *
 * Clean 2-dp values are produced by working in INTEGER UNITS of 0.01% (so 0.39%
 * = 39 units). We seed each day near the average, clamp into range, then nudge
 * ±1 unit on random eligible days until the units sum exactly to the target.
 */
class RoiScheduleService
{
    public function __construct(protected SettingsService $settings) {}

    public function isMonthlyMode(): bool
    {
        return $this->settings->get('roi_mode') === 'monthly_target';
    }

    /** The pre-generated daily % for a date, or null if none exists. */
    public function rateFor(string|Carbon $date): ?float
    {
        $d = $date instanceof Carbon ? $date->toDateString() : Carbon::parse($date)->toDateString();
        $row = RoiDailyRate::whereDate('rate_date', $d)->first();
        return $row ? (float) $row->percent : null;
    }

    /**
     * Make sure a schedule exists covering $date's month (lazy generation at the
     * first ROI run of a new month). Never overwrites existing rows.
     */
    public function ensureMonth(string|Carbon $date): void
    {
        $month = ($date instanceof Carbon ? $date->copy() : Carbon::parse($date));
        if (RoiDailyRate::whereDate('rate_date', $month->toDateString())->exists()) {
            return; // today already scheduled
        }
        $this->generateMonth($month, false);
    }

    /**
     * Build (or rebuild) the daily schedule for the month containing $month.
     *
     * Already-paid days (up to the last date ROI ran this month) are kept at their
     * recorded rate (or the flat rate for days that predate the switch) and the
     * remaining target is distributed over the days still to come. Future ("locked"
     * = false) days are (re)generated. Returns a result array; on an infeasible
     * target it generates nothing and returns ['ok' => false, ...].
     *
     * @return array{ok:bool,error?:string,days?:int,target?:float,remaining_target?:float,generated_days?:int}
     */
    public function generateMonth(Carbon $month, bool $regenerate = true): array
    {
        $start = $month->copy()->startOfMonth();
        $end   = $month->copy()->endOfMonth();
        $N     = (int) $start->daysInMonth;

        $target = round((float) $this->settings->get('roi_monthly_target'), 2);
        $min    = round((float) $this->settings->get('roi_daily_min'), 2);
        $max    = round((float) $this->settings->get('roi_daily_max'), 2);
        $flat   = round((float) $this->settings->get('roi_daily_percent'), 2);

        if ($min > $max) {
            return ['ok' => false, 'error' => 'Min harian tidak boleh melebihi max harian.'];
        }

        // Last date ROI actually ran this month (global) → everything up to and
        // including it is "already paid" and must be preserved.
        $lastPaid    = RoiLog::whereBetween('roi_date', [$start->toDateString(), $end->toDateString()])->max('roi_date');
        $lastPaidDay = $lastPaid ? Carbon::parse($lastPaid)->day : 0;

        $existing = RoiDailyRate::whereBetween('rate_date', [$start->toDateString(), $end->toDateString()])
            ->get()->keyBy(fn ($r) => Carbon::parse($r->rate_date)->day);

        // Preserve the elapsed/paid days at the rate that was ACTUALLY paid that
        // day (derived from roi_logs), so the locked days match the real payouts
        // and the remaining target is deducted correctly. Falls back to an
        // existing schedule row, then the flat rate, if a day has no logs.
        $actual = $this->actualPaidRates($start, $start->copy()->day(max($lastPaidDay, 1)));
        $fixed = [];
        $fixedSum = 0.0;
        for ($d = 1; $d <= $lastPaidDay; $d++) {
            $rate = $actual[$d]
                ?? (isset($existing[$d]) ? round((float) $existing[$d]->percent, 2) : $flat);
            $fixed[$d] = $rate;
            $fixedSum += $rate;
        }
        $fixedSum = round($fixedSum, 2);

        $remainingDays   = $N - $lastPaidDay;
        $remainingTarget = round($target - $fixedSum, 2);

        if ($remainingDays <= 0) {
            // Whole month already elapsed — just persist the fixed rows.
            $this->persist($start, $fixed, [], $lastPaidDay);
            return ['ok' => true, 'days' => $N, 'target' => $target, 'remaining_target' => 0.0, 'generated_days' => 0];
        }

        if ($remainingTarget < 0) {
            return ['ok' => false, 'error' => "Sudah dibayar {$fixedSum}% bulan ini melebihi target {$target}%."];
        }

        $generated = $this->generate($remainingTarget, $min, $max, $remainingDays);
        if ($generated === null) {
            $loBound = round($min * $remainingDays, 2);
            $hiBound = round($max * $remainingDays, 2);
            return ['ok' => false, 'error' =>
                "Baki target {$remainingTarget}% untuk {$remainingDays} hari mesti antara {$loBound}% (min×hari) dan {$hiBound}% (max×hari). Laraskan target atau julat min/max.",
            ];
        }

        $this->persist($start, $fixed, $generated, $lastPaidDay);

        return [
            'ok'               => true,
            'days'             => $N,
            'target'           => $target,
            'remaining_target' => $remainingTarget,
            'generated_days'   => $remainingDays,
        ];
    }

    /**
     * The daily ROI % that was ACTUALLY paid on each date in the range, derived
     * from roi_logs: for an uncapped package amount = principal × rate%, so
     * rate = amount / principal × 100. We take the MAX across that day's packages
     * (packages near the 200% cap pay less, so the max is the true daily rate).
     *
     * @return array<int,float>  day-of-month => rate %
     */
    protected function actualPaidRates(Carbon $start, Carbon $end): array
    {
        $rows = DB::table('roi_logs as rl')
            ->join('investment_packages as p', 'p.id', '=', 'rl.investment_package_id')
            ->whereBetween('rl.roi_date', [$start->toDateString(), $end->toDateString()])
            ->where('p.principal', '>', 0)
            ->groupBy('rl.roi_date')
            ->selectRaw('rl.roi_date as d, MAX(rl.amount / p.principal * 100) as pct')
            ->get();

        $map = [];
        foreach ($rows as $r) {
            $map[(int) Carbon::parse($r->d)->day] = round((float) $r->pct, 2);
        }
        return $map;
    }

    /** Upsert the month's rows. $fixed (locked) + $generated (future, unlocked). */
    protected function persist(Carbon $start, array $fixed, array $generated, int $lastPaidDay): void
    {
        $rows = [];
        foreach ($fixed as $day => $pct) {
            $rows[$day] = ['percent' => $pct, 'locked' => true];
        }
        foreach (array_values($generated) as $i => $pct) {
            $day = $lastPaidDay + $i + 1;
            $rows[$day] = ['percent' => $pct, 'locked' => false];
        }
        foreach ($rows as $day => $r) {
            $dateStr = $start->copy()->day($day)->toDateString();
            RoiDailyRate::updateOrCreate(
                ['rate_date' => $dateStr],
                ['percent' => $r['percent'], 'locked' => $r['locked']]
            );
        }
    }

    /**
     * Generate $N clean 2-dp daily values within [$min, $max] that sum EXACTLY to
     * $target. Works in integer units of 0.01%. Returns null when infeasible.
     *
     * @return float[]|null
     */
    public function generate(float $target, float $min, float $max, int $N): ?array
    {
        if ($N < 1) {
            return null;
        }
        $U  = fn (float $x) => (int) round($x * 100);
        $Tu = $U($target);
        $mn = $U($min);
        $mx = $U($max);

        if ($mn > $mx || $Tu < $mn * $N || $Tu > $mx * $N) {
            return null;
        }

        $baseU = $Tu / $N;
        $v = [];
        for ($i = 0; $i < $N; $i++) {
            $spread = min($baseU - $mn, $mx - $baseU);
            $x = (int) round($baseU + (mt_rand() / mt_getrandmax() * 2 - 1) * $spread * 0.95);
            $v[$i] = max($mn, min($mx, $x));
        }

        // Nudge ±1 unit on random eligible days until the sum lands on target.
        $guard = 0;
        while ($guard++ < 200000) {
            $s    = array_sum($v);
            $diff = $Tu - $s;
            if ($diff === 0) {
                break;
            }
            $step = $diff > 0 ? 1 : -1;
            $elig = [];
            for ($i = 0; $i < $N; $i++) {
                if ($step > 0 ? $v[$i] < $mx : $v[$i] > $mn) {
                    $elig[] = $i;
                }
            }
            if (empty($elig)) {
                break;
            }
            $v[$elig[array_rand($elig)]] += $step;
        }

        if (array_sum($v) !== $Tu) {
            return null; // could not balance within range (shouldn't happen when feasible)
        }

        return array_map(fn ($u) => round($u / 100, 2), $v);
    }

    /**
     * Current-month schedule for the admin preview: each day's %, whether it is
     * locked (already paid) and the running total, plus target/sum checks.
     */
    public function currentMonthPreview(?Carbon $month = null): array
    {
        $month = $month ? $month->copy() : Carbon::today();
        $start = $month->copy()->startOfMonth();
        $end   = $month->copy()->endOfMonth();
        $today = Carbon::today()->toDateString();

        $rows = RoiDailyRate::whereBetween('rate_date', [$start->toDateString(), $end->toDateString()])
            ->orderBy('rate_date')->get();

        $days = [];
        $cum  = 0.0;
        foreach ($rows as $r) {
            $pct = (float) $r->percent;
            $cum = round($cum + $pct, 2);
            $dateStr = Carbon::parse($r->rate_date)->toDateString();
            $days[] = [
                'date'       => $dateStr,
                'percent'    => $pct,
                'locked'     => (bool) $r->locked,
                'cumulative' => $cum,
                'is_today'   => $dateStr === $today,
            ];
        }

        return [
            'month'      => $start->format('Y-m'),
            'month_name' => $start->format('F Y'),
            'days'       => $days,
            'sum'        => $cum,
            'target'     => round((float) $this->settings->get('roi_monthly_target'), 2),
            'min'        => round((float) $this->settings->get('roi_daily_min'), 2),
            'max'        => round((float) $this->settings->get('roi_daily_max'), 2),
            'mode'       => $this->settings->get('roi_mode'),
        ];
    }
}
