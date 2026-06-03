<?php

namespace App\Services;

use App\Models\User;
use App\Models\Withdrawal;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Withdrawals from E-WALLET. On request the gross amount is held (debited) from
 * E-WALLET and a pending record is created. Admin approve finalises (sets txid);
 * reject refunds the held amount back to E-WALLET.
 */
class WithdrawalService
{
    public function __construct(protected WalletService $wallets, protected SettingsService $settings) {}

    public function request(User $user, $amount, string $walletAddress): Withdrawal
    {
        $min     = (float) $this->settings->get('min_withdrawal');
        $maxDay  = (float) $this->settings->get('max_withdrawal_daily');
        $feePct  = (float) $this->settings->get('withdrawal_fee_percent');
        $amount  = (float) $amount;

        if ($amount < $min) {
            throw ValidationException::withMessages(['amount' => "Minimum withdrawal is {$min} USDT."]);
        }

        // Daily cap: sum of today's non-rejected withdrawals + this request
        $todayTotal = Withdrawal::where('user_id', $user->id)
            ->where('status', '!=', 'rejected')
            ->whereDate('created_at', Carbon::today())
            ->sum('amount');

        if (($todayTotal + $amount) > $maxDay) {
            throw ValidationException::withMessages([
                'amount' => "Daily withdrawal limit is {$maxDay} USDT. Already requested today: {$todayTotal} USDT.",
            ]);
        }

        $amountStr = number_format($amount, 8, '.', '');
        $fee       = bcdiv(bcmul($amountStr, (string) $feePct, 10), '100', 8);
        $net       = bcsub($amountStr, $fee, 8);

        return DB::transaction(function () use ($user, $amountStr, $fee, $net, $walletAddress) {
            $withdrawal = Withdrawal::create([
                'user_id'        => $user->id,
                'amount'         => $amountStr,
                'fee'            => $fee,
                'net_amount'     => $net,
                'wallet_address' => $walletAddress,
                'status'         => 'pending',
            ]);

            // Hold funds out of E-WALLET immediately
            $this->wallets->debit($user, 'E', $amountStr, 'withdrawal', $withdrawal, ['hold' => true]);

            return $withdrawal;
        });
    }

    public function approve(Withdrawal $withdrawal, User $admin, ?string $txid = null): Withdrawal
    {
        if ($withdrawal->status !== 'pending') {
            return $withdrawal;
        }
        $withdrawal->update([
            'status'       => 'approved',
            'txid'         => $txid,
            'processed_by' => $admin->id,
            'processed_at' => now(),
        ]);
        return $withdrawal;
    }

    public function reject(Withdrawal $withdrawal, User $admin, ?string $note = null): Withdrawal
    {
        if ($withdrawal->status !== 'pending') {
            return $withdrawal;
        }
        DB::transaction(function () use ($withdrawal, $admin, $note) {
            // Refund the held gross amount
            $this->wallets->credit($withdrawal->user, 'E', $withdrawal->amount, 'withdrawal_refund', $withdrawal, ['refund' => true]);
            $withdrawal->update([
                'status'       => 'rejected',
                'processed_by' => $admin->id,
                'processed_at' => now(),
                'note'         => $note,
            ]);
        });
        return $withdrawal;
    }
}
