<?php

namespace App\Console\Commands;

use App\Models\MatchingBonusLog;
use App\Models\RoiLog;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Services\MatchingBonusService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Rebuild ALL matching bonuses: reverse the old matching credits from E-WALLET
 * balances, delete the old matching logs + ledger rows, then recompute matching
 * for every roi_log using the current (corrected) MatchingBonusService.
 *
 * Safe to re-run. Wrap in a transaction so it's all-or-nothing.
 */
class RebuildMatching extends Command
{
    protected $signature = 'matching:rebuild {--dry : Show what would change without writing}';
    protected $description = 'Delete all matching bonuses and recompute from roi_logs with the current engine.';

    public function handle(MatchingBonusService $svc): int
    {
        $oldLogs    = MatchingBonusLog::count();
        $oldCredited = (float) WalletTransaction::where('type', 'matching')->where('direction', 'credit')->sum('amount');
        $roiCount   = RoiLog::count();

        $this->table(['Existing matching logs', 'Total old credited', 'ROI logs to reprocess'],
            [[$oldLogs, number_format($oldCredited, 8) . ' USDT', $roiCount]]);

        if ($this->option('dry')) {
            $this->warn('Dry run — no changes made.');
            return self::SUCCESS;
        }

        DB::transaction(function () use ($svc) {
            // 1) Reverse old matching credits from E-WALLET balances.
            $byUser = WalletTransaction::where('type', 'matching')->where('direction', 'credit')
                ->select('user_id', DB::raw('SUM(amount) as total'))
                ->groupBy('user_id')->get();
            foreach ($byUser as $row) {
                Wallet::where('user_id', $row->user_id)->where('type', 'E')->decrement('balance', $row->total);
            }

            // 2) Delete old matching ledger rows + logs.
            WalletTransaction::where('type', 'matching')->delete();
            MatchingBonusLog::query()->delete();

            // 3) Recompute matching for every ROI payout with the current engine.
            RoiLog::with('user')->orderBy('id')->chunkById(500, function ($logs) use ($svc) {
                foreach ($logs as $roiLog) {
                    $svc->distributeForRoi($roiLog);
                }
            });
        });

        $newLogs     = MatchingBonusLog::count();
        $newCredited = (float) WalletTransaction::where('type', 'matching')->where('direction', 'credit')->sum('amount');

        $this->info("Rebuilt. New matching logs: {$newLogs}. New total credited: " . number_format($newCredited, 8) . ' USDT.');
        return self::SUCCESS;
    }
}
