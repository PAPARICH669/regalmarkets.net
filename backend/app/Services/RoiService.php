<?php

namespace App\Services;

use App\Models\InvestmentPackage;
use App\Models\RoiLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Daily ROI engine.
 *
 * The daily profit rate is VARIABLE and set manually by the admin (the
 * `roi_daily_percent` setting, or a per-run override). Each active package pays:
 *
 *     payout = min( dailyPercent% × principal , remaining )
 *
 * into E-WALLET, logs it (idempotent per package+date), advances total_paid, and
 * the remaining ROI (total_return − total_paid) shrinks until the 200% cap, at
 * which point the package completes. Each payout fires the matching rollup.
 */
class RoiService
{
    public function __construct(
        protected WalletService $wallets,
        protected MatchingBonusService $matching,
        protected SettingsService $settings,
    ) {}

    /**
     * Run ROI for a date (default today). $percent overrides the configured
     * daily rate for this run only (admin manual rate). Returns summary stats.
     */
    public function runForDate(?Carbon $date = null, ?float $percent = null): array
    {
        $date    = ($date ?? Carbon::today())->toDateString();
        $percent = $percent ?? (float) $this->settings->get('roi_daily_percent');

        $stats = ['paid' => 0, 'amount' => '0', 'completed' => 0, 'skipped' => 0, 'percent' => $percent];

        InvestmentPackage::active()->with('user')->chunkById(200, function ($packages) use ($date, $percent, &$stats) {
            foreach ($packages as $package) {
                $result = $this->payPackage($package, $date, $percent);
                if ($result === null) {
                    $stats['skipped']++;
                    continue;
                }
                $stats['paid']++;
                $stats['amount'] = bcadd($stats['amount'], $result['amount'], 8);
                if ($result['completed']) {
                    $stats['completed']++;
                }
            }
        });

        return $stats;
    }

    /** @return array{amount:string,completed:bool}|null  null when nothing paid */
    protected function payPackage(InvestmentPackage $package, string $date, float $percent): ?array
    {
        // Commission starts the DAY AFTER funding. Skip packages activated on or
        // after this ROI date (i.e. funded "today" earns its first payout tomorrow).
        if ($package->activated_at && $package->activated_at->toDateString() >= $date) {
            return null;
        }

        // Idempotency guard outside the transaction (fast path)
        $already = RoiLog::where('investment_package_id', $package->id)
            ->whereDate('roi_date', $date)->exists();
        if ($already) {
            return null;
        }

        return DB::transaction(function () use ($package, $date, $percent) {
            $package = InvestmentPackage::lockForUpdate()->find($package->id);
            if (! $package || $package->status !== 'active') {
                return null;
            }

            $remaining = bcsub($package->total_return, $package->total_paid, 8);
            if (bccomp($remaining, '0', 8) <= 0) {
                $package->update(['status' => 'completed', 'completed_at' => now()]);
                return null;
            }

            // Daily profit = manual daily% × principal (capped at the remaining ROI).
            $dailyAmount = bcdiv(bcmul($package->principal, (string) $percent, 10), '100', 8);
            $payout = bccomp($dailyAmount, $remaining, 8) > 0 ? $remaining : $dailyAmount;

            if (bccomp($payout, '0', 8) <= 0) {
                return null;
            }

            $roiLog = RoiLog::create([
                'investment_package_id' => $package->id,
                'user_id'               => $package->user_id,
                'amount'                => $payout,
                'roi_date'              => $date,
            ]);

            $this->wallets->credit($package->user, 'E', $payout, 'roi', $roiLog, [
                'package_id' => $package->id,
                'percent'    => $percent,
            ]);

            $newPaid    = bcadd($package->total_paid, $payout, 8);
            $isComplete = bccomp($newPaid, $package->total_return, 8) >= 0;
            $package->update([
                'total_paid'        => $newPaid,
                'daily_roi_percent' => $percent,
                'daily_amount'      => $dailyAmount,
                'status'            => $isComplete ? 'completed' : 'active',
                'completed_at'      => $isComplete ? now() : null,
            ]);

            // When the package hits 200%, remove its principal from the member's
            // total_fund so only still-earning (incomplete) capital remains counted.
            if ($isComplete) {
                $u = User::lockForUpdate()->find($package->user_id);
                if ($u) {
                    $newFund = bcsub((string) $u->total_fund, (string) $package->principal, 8);
                    $u->update(['total_fund' => bccomp($newFund, '0', 8) < 0 ? '0' : $newFund]);
                }
            }

            // Matching bonus rollup off this ROI payout.
            $this->matching->distributeForRoi($roiLog);

            return ['amount' => $payout, 'completed' => $isComplete];
        });
    }
}
