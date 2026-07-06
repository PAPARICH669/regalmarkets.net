<?php

namespace App\Console\Commands;

use App\Models\Deposit;
use App\Services\BscVerifier;
use App\Services\DepositService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Auto-deposit (Cadangan A) follow-up: re-check deposits still in `verifying`.
 * Credits them once confirmations are sufficient, updates the running
 * confirmation count, and rejects ones that never appear on-chain after a grace
 * period (wrong/mistyped hash). Never touches `review` (manual) or `approved`.
 */
class VerifyPendingDeposits extends Command
{
    protected $signature = 'deposits:verify-pending';
    protected $description = 'Re-check on-chain status of verifying auto-deposits and credit confirmed ones.';

    public function handle(BscVerifier $verifier, DepositService $deposits): int
    {
        if (! config('regal.deposit.auto_verify') || ! $verifier->isConfigured()) {
            $this->info('Auto-deposit disabled — nothing to do.');
            return self::SUCCESS;
        }

        $minConf   = (int) config('regal.deposit.min_confirmations');
        $graceMins = 120; // reject a never-found tx after 2 hours
        $credited  = 0; $rejected = 0;

        Deposit::where('status', 'verifying')->whereNotNull('tx_hash')
            ->orderBy('id')->limit(200)->get()
            ->each(function (Deposit $d) use ($verifier, $deposits, $minConf, $graceMins, &$credited, &$rejected) {
                $r = $verifier->verify($d->tx_hash);

                if ($r['error'] === 'api_error') {
                    return; // transient, try again next tick
                }

                if (! $r['found']) {
                    if ($d->created_at && $d->created_at->diffInMinutes(now()) >= $graceMins) {
                        $d->update(['status' => 'rejected', 'note' => 'Transaction not found on-chain after verification window.']);
                        $rejected++;
                    }
                    return;
                }

                if (! $r['success'] || ! $r['matched'] || bccomp($r['amount'], '0', 8) <= 0) {
                    $d->update(['status' => 'rejected', 'note' => 'Not a valid USDT transfer to the deposit address.']);
                    $rejected++;
                    return;
                }

                // Backfill on-chain facts as they become available.
                $d->update([
                    'amount'        => $r['amount'],
                    'from_address'  => $r['from'] ?? $d->from_address,
                    'block_number'  => $r['block'] ?: $d->block_number,
                    'confirmations' => $r['confirmations'],
                ]);

                if ($r['confirmations'] >= $minConf) {
                    $deposits->confirmAuto($d);
                    app(\App\Services\TelegramService::class)->notify('💰 Auto Deposit (credited)', [
                        'User'   => '@' . $d->user->username,
                        'Amount' => number_format((float) $d->amount, 2) . ' USDT',
                        'TX'     => $d->tx_hash,
                    ]);
                    $credited++;
                }
            });

        $this->info("Verify-pending done. Credited: {$credited}, rejected: {$rejected}.");
        if ($credited || $rejected) {
            Log::info("deposits:verify-pending credited={$credited} rejected={$rejected}");
        }
        return self::SUCCESS;
    }
}
