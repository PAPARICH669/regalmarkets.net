<?php

namespace App\Services;

use App\Models\InvestmentPackage;
use App\Models\RoiLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Daily ROI engine. For each active package, pays min(daily_amount, remaining)
 * into E-WALLET, logs it (idempotent per package+date), advances total_paid,
 * completes the package at 200%, then fires the matching bonus rollup.
 */
class RoiService
{
    public function __construct(
        protected WalletService $wallets,
        protected MatchingBonusService $matching,
    ) {}

    /** Run ROI for a given date (defaults to today). Returns summary stats. */
    public function runForDate(?Carbon $date = null): array
    {
        $date = ($date ?? Carbon::today())->toDateString();

        $stats = ['paid' => 0, 'amount' => '0', 'completed' => 0, 'skipped' => 0];

        InvestmentPackage::active()->with('user')->chunkById(200, function ($packages) use ($date, &$stats) {
            foreach ($packages as $package) {
                $result = $this->payPackage($package, $date);
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
    protected function payPackage(InvestmentPackage $package, string $date): ?array
    {
        // Idempotency guard outside the transaction (fast path)
        $already = RoiLog::where('investment_package_id', $package->id)
            ->whereDate('roi_date', $date)->exists();
        if ($already) {
            return null;
        }

        return DB::transaction(function () use ($package, $date) {
            $package = InvestmentPackage::lockForUpdate()->find($package->id);
            if (! $package || $package->status !== 'active') {
                return null;
            }

            $remaining = bcsub($package->total_return, $package->total_paid, 8);
            if (bccomp($remaining, '0', 8) <= 0) {
                $package->update(['status' => 'completed', 'completed_at' => now()]);
                return null;
            }

            $payout = bccomp($package->daily_amount, $remaining, 8) > 0
                ? $remaining
                : $package->daily_amount;

            $roiLog = RoiLog::create([
                'investment_package_id' => $package->id,
                'user_id'               => $package->user_id,
                'amount'                => $payout,
                'roi_date'              => $date,
            ]);

            $this->wallets->credit($package->user, 'E', $payout, 'roi', $roiLog, [
                'package_id' => $package->id,
            ]);

            $newPaid    = bcadd($package->total_paid, $payout, 8);
            $isComplete = bccomp($newPaid, $package->total_return, 8) >= 0;
            $package->update([
                'total_paid'   => $newPaid,
                'status'       => $isComplete ? 'completed' : 'active',
                'completed_at' => $isComplete ? now() : null,
            ]);

            // Matching bonus rollup off this ROI payout.
            $this->matching->distributeForRoi($roiLog);

            return ['amount' => $payout, 'completed' => $isComplete];
        });
    }
}
