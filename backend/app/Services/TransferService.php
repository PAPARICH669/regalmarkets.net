<?php

namespace App\Services;

use App\Models\Transfer;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Transfers. Two allowed flows:
 *   - selfEtoA:        E-WALLET → A-WALLET (own account).  A → E is NEVER allowed.
 *   - memberToMember:  A-WALLET → another member's A-WALLET.
 */
class TransferService
{
    public function __construct(protected WalletService $wallets, protected SettingsService $settings) {}

    public function selfEtoA(User $user, $amount): Transfer
    {
        $amount = $this->validateAmount($amount);

        // Charge a percentage fee on E-WALLET → A-WALLET transfers. The full
        // amount leaves E-WALLET; only the net (amount − fee) lands in A-WALLET.
        $feePct = (float) $this->settings->get('transfer_fee_percent');
        $fee    = bcdiv(bcmul($amount, (string) $feePct, 10), '100', 8);
        $net    = bcsub($amount, $fee, 8);
        if (bccomp($net, '0', 8) <= 0) {
            throw ValidationException::withMessages(['amount' => 'Amount is too small after the transfer fee.']);
        }

        return DB::transaction(function () use ($user, $amount, $fee, $net, $feePct) {
            $this->wallets->debit($user, 'E', $amount, 'transfer_out', null, ['flow' => 'E->A self', 'fee' => $fee, 'fee_percent' => $feePct]);
            $this->wallets->credit($user, 'A', $net, 'transfer_in', null, ['flow' => 'E->A self', 'fee' => $fee, 'fee_percent' => $feePct]);

            return Transfer::create([
                'from_user_id' => $user->id,
                'to_user_id'   => null,
                'from_wallet'  => 'E',
                'to_wallet'    => 'A',
                'amount'       => $amount,
                'fee'          => $fee,
                'net_amount'   => $net,
                'type'         => 'internal_self',
            ]);
        });
    }

    public function memberToMember(User $from, string $toUsername, $amount): Transfer
    {
        $amount = $this->validateAmount($amount);

        $to = User::where('username', $toUsername)->orWhere('email', $toUsername)->first();
        if (! $to) {
            throw ValidationException::withMessages(['to' => 'Recipient not found.']);
        }
        if ($to->id === $from->id) {
            throw ValidationException::withMessages(['to' => 'Cannot transfer to yourself.']);
        }
        if ($to->is_frozen) {
            throw ValidationException::withMessages(['to' => 'Recipient account is frozen.']);
        }

        return DB::transaction(function () use ($from, $to, $amount) {
            $this->wallets->debit($from, 'A', $amount, 'transfer_out', $to, ['flow' => 'A->A member', 'to' => $to->id]);
            $this->wallets->credit($to, 'A', $amount, 'transfer_in', $from, ['flow' => 'A->A member', 'from' => $from->id]);

            return Transfer::create([
                'from_user_id' => $from->id,
                'to_user_id'   => $to->id,
                'from_wallet'  => 'A',
                'to_wallet'    => 'A',
                'amount'       => $amount,
                'fee'          => 0,
                'net_amount'   => $amount,
                'type'         => 'member_to_member',
            ]);
        });
    }

    protected function validateAmount($amount): string
    {
        $min = (float) $this->settings->get('min_transfer');
        if ((float) $amount < $min) {
            throw ValidationException::withMessages(['amount' => "Minimum transfer is {$min} USDT."]);
        }
        return number_format((float) $amount, 8, '.', '');
    }
}
