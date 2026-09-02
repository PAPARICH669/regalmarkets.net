<?php

namespace App\Services;

use App\Models\User;
use App\Models\Withdrawal;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Withdrawals from E-WALLET. On request the gross USDT amount is held (debited)
 * from E-WALLET and a pending record is created. Admin approve finalises (sets
 * txid + actual coin amount for swaps); reject refunds the held amount.
 *
 * A member may RECEIVE the payout in USDT (default) or, for a coin swap, in
 * BTC / ETH / SOL (all BEP20). The USDT amount is always what leaves the wallet;
 * the coin the member receives is an estimate (CoinGecko + markup), finalised by
 * the admin. The venue used to swap is never exposed to the member.
 */
class WithdrawalService
{
    public function __construct(
        protected WalletService $wallets,
        protected SettingsService $settings,
        protected CoinSwapService $coins,
    ) {}

    public function request(User $user, $amount, string $coin, ?string $network, string $walletAddress): Withdrawal
    {
        $coin      = strtoupper(trim($coin));
        $network   = $network ? strtoupper(trim($network)) : null;
        $maxAmount = (float) $this->settings->get('max_withdrawal_daily');
        $maxPerDay = (int) $this->settings->get('withdrawal_max_per_day');
        $amount    = (float) $amount;

        // Withdrawal requests are only accepted within the daily time window.
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

        if ($amount > $maxAmount) {
            throw ValidationException::withMessages(['amount' => "Maximum withdrawal is {$maxAmount} USDT."]);
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

        // --- Coin-specific accounting -------------------------------------------------
        $network        = 'BEP20';
        $systemRate     = '1.00000000';
        $coinFee        = '0';
        $coinAmountEst  = '0';

        if ($coin === 'USDT') {
            // Unchanged legacy behaviour: flat USDT fee, net = amount - fee.
            $min     = (float) $this->settings->get('min_withdrawal');
            $feeFlat = (float) $this->settings->get('withdrawal_fee');
            if ($amount < $min) {
                throw ValidationException::withMessages(['amount' => "Minimum withdrawal is {$min} USDT."]);
            }
            if ($amount <= $feeFlat) {
                throw ValidationException::withMessages(['amount' => "Amount must be greater than the {$feeFlat} USDT fee."]);
            }
            $amountStr     = number_format($amount, 8, '.', '');
            $fee           = number_format($feeFlat, 8, '.', '');
            $net           = bcsub($amountStr, $fee, 8);
            $coinFee       = number_format($feeFlat, 12, '.', '');   // fee in USDT (coin unit = USDT)
            $coinAmountEst = number_format((float) $net, 12, '.', '');
        } else {
            // Coin swap: no flat USDT fee — a network fee (in coin) is deducted from
            // the coin the member receives instead. The full USDT amount is debited.
            if (! $this->coins->enabled() || ! $this->coins->isCoin($coin)) {
                throw ValidationException::withMessages(['coin' => 'This coin is not available for withdrawal.']);
            }
            if ($this->coins->network($coin, $network) === null) {
                throw ValidationException::withMessages(['network' => 'Invalid network for this coin.']);
            }
            $quote = $this->coins->quote($coin, $network, $amount);
            if ($quote === null) {
                throw ValidationException::withMessages([
                    'coin' => 'Live price is temporarily unavailable. Please try again in a moment.',
                ]);
            }
            if (! $quote['meets_min']) {
                $m = number_format($quote['min_usdt'], 2);
                throw ValidationException::withMessages([
                    'amount' => "Minimum for {$coin} is {$m} USDT (≥ " . rtrim(rtrim(number_format($quote['min_coin'], $quote['dp'], '.', ''), '0'), '.') . " {$coin}).",
                ]);
            }
            $network       = $quote['network'];
            $amountStr     = number_format($amount, 8, '.', '');
            $fee           = '0.00000000';                          // no flat USDT fee on swaps
            $net           = $amountStr;                            // full USDT debited & converted
            $systemRate    = number_format($quote['system_rate'], 8, '.', '');
            $coinFee       = number_format($quote['fee_coin'], 12, '.', '');
            $coinAmountEst = number_format($quote['net_coin'], 12, '.', '');
        }

        // Insufficient balance is a normal user mistake — friendly 422 (not a 500).
        $balance = $this->wallets->balance($user, 'E');
        if (bccomp($balance, $amountStr, 8) < 0) {
            throw ValidationException::withMessages([
                'amount' => 'Insufficient E-Wallet balance. Your balance is ' . number_format((float) $balance, 2) . ' USDT.',
            ]);
        }

        return DB::transaction(function () use ($user, $amountStr, $fee, $net, $walletAddress, $coin, $network, $systemRate, $coinFee, $coinAmountEst) {
            $withdrawal = Withdrawal::create([
                'user_id'         => $user->id,
                'amount'          => $amountStr,
                'fee'             => $fee,
                'net_amount'      => $net,
                'wallet_address'  => $walletAddress,   // payout address for THIS withdrawal
                'coin'            => $coin,
                'network'         => $network,
                'coin_address'    => $walletAddress,
                'system_rate'     => $systemRate,
                'coin_fee'        => $coinFee,
                'coin_amount_est' => $coinAmountEst,
                'status'          => 'pending',
            ]);

            // Hold the gross USDT out of E-WALLET immediately.
            $this->wallets->debit($user, 'E', $amountStr, 'withdrawal', $withdrawal, ['hold' => true]);

            return $withdrawal;
        });
    }

    public function approve(Withdrawal $withdrawal, User $admin, ?string $txid = null, $coinActual = null): Withdrawal
    {
        if ($withdrawal->status !== 'pending') {
            return $withdrawal;
        }
        $update = [
            'status'       => 'approved',
            'txid'         => $txid,
            'processed_by' => $admin->id,
            'processed_at' => now(),
        ];
        // For a coin swap, record the ACTUAL coin amount the admin sent (source of truth).
        if ($withdrawal->coin !== 'USDT' && $coinActual !== null && $coinActual !== '') {
            $update['coin_amount_actual'] = number_format((float) $coinActual, 12, '.', '');
        }
        $withdrawal->update($update);
        return $withdrawal;
    }

    public function reject(Withdrawal $withdrawal, User $admin, ?string $note = null): Withdrawal
    {
        if ($withdrawal->status !== 'pending') {
            return $withdrawal;
        }
        DB::transaction(function () use ($withdrawal, $admin, $note) {
            // Refund the held gross USDT amount.
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
