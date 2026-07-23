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
        $min       = (float) $this->settings->get('min_withdrawal');
        $maxAmount = (float) $this->settings->get('max_withdrawal_daily'); // max per withdrawal
        $feeFlat   = (float) $this->settings->get('withdrawal_fee');       // flat fee in USDT
        $maxPerDay = (int) $this->settings->get('withdrawal_max_per_day'); // withdrawals allowed / day
        $amount    = (float) $amount;

        // Withdrawal requests are only accepted within the daily time window
        // (e.g. 09:00–12:00 Asia/Kuala_Lumpur).
        $tz    = config('app.timezone', 'Asia/Kuala_Lumpur');
        $now   = Carbon::now($tz);
        $wStart = $this->settings->get('withdrawal_window_start');
        $wEnd   = $this->settings->get('withdrawal_window_end');
        $start  = Carbon::createFromFormat('H:i', $wStart, $tz)->setDateFrom($now);
        $end    = Carbon::createFromFormat('H:i', $wEnd, $tz)->setDateFrom($now);
        if ($now->lt($start) || $now->gte($end)) {
            throw ValidationException::withMessages([
                'amount' => "Withdrawals can only be requested between {$wStart} and {$wEnd}.",
            ]);
        }

        if ($amount < $min) {
            throw ValidationException::withMessages(['amount' => "Minimum withdrawal is {$min} USDT."]);
        }
        if ($amount > $maxAmount) {
            throw ValidationException::withMessages(['amount' => "Maximum withdrawal is {$maxAmount} USDT."]);
        }
        if ($amount <= $feeFlat) {
            throw ValidationException::withMessages(['amount' => "Amount must be greater than the {$feeFlat} USDT fee."]);
        }

        // Only N withdrawals per day (count of today's non-rejected requests).
        $todayCount = Withdrawal::where('user_id', $user->id)
            ->where('status', '!=', 'rejected')
            ->whereDate('created_at', Carbon::today())
            ->count();

        if ($todayCount >= $maxPerDay) {
            throw ValidationException::withMessages([
                'amount' => "You can only withdraw {$maxPerDay} time(s) per day. Try again tomorrow.",
            ]);
        }

        $amountStr = number_format($amount, 8, '.', '');
        $fee       = number_format($feeFlat, 8, '.', '');
        $net       = bcsub($amountStr, $fee, 8);

        // Insufficient balance is a normal user mistake — return a friendly 422,
        // not a 500 from the wallet debit throwing "Insufficient E-WALLET balance".
        $balance = $this->wallets->balance($user, 'E');
        if (bccomp($balance, $amountStr, 8) < 0) {
            throw ValidationException::withMessages([
                'amount' => 'Insufficient E-Wallet balance. Your balance is ' . number_format((float) $balance, 2) . ' USDT.',
            ]);
        }

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
