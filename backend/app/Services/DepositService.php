<?php

namespace App\Services;

use App\Models\Deposit;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Deposit lifecycle. Users request (pending); admin approves → the funds land in
 * the member's A-WALLET (capital) and drive their fund/rank. The member then
 * separately clicks "Fund" to lock A-WALLET capital into a 200% package — that
 * step (FundService) is what starts the daily commission and pays sponsor bonus.
 */
class DepositService
{
    public function __construct(
        protected WalletService $wallets,
        protected RankService $ranks,
    ) {}

    public function request(User $user, $amount, ?string $txid = null, ?string $proofPath = null): Deposit
    {
        return Deposit::create([
            'user_id'    => $user->id,
            'amount'     => number_format((float) $amount, 8, '.', ''),
            'txid'       => $txid,
            'proof_path' => $proofPath,
            'status'     => 'pending',
        ]);
    }

    public function approve(Deposit $deposit, User $admin): Deposit
    {
        if ($deposit->status === 'approved') {
            return $deposit;
        }

        $this->credit($deposit, ['approved_by' => $admin->id]);
        return $deposit->refresh();
    }

    /**
     * Auto-deposit (Cadangan A): credit a deposit that was verified on-chain,
     * with the SYSTEM as the actor (no admin). Called by DepositController /
     * the verify-pending scheduler once confirmations are sufficient.
     */
    public function confirmAuto(Deposit $deposit): Deposit
    {
        if ($deposit->status === 'approved') {
            return $deposit;
        }

        $this->credit($deposit, ['approved_by' => null, 'verified_at' => now()]);
        return $deposit->refresh();
    }

    /**
     * Shared crediting path: A-WALLET credit + total_fund + rank refresh, inside
     * one transaction. Guarded so a deposit is credited at most once.
     */
    protected function credit(Deposit $deposit, array $extra = []): void
    {
        DB::transaction(function () use ($deposit, $extra) {
            // Re-read under lock to prevent a double-credit race (admin + scheduler).
            $fresh = Deposit::whereKey($deposit->id)->lockForUpdate()->first();
            if (! $fresh || $fresh->status === 'approved') {
                return;
            }

            $fresh->update(array_merge([
                'status'      => 'approved',
                'approved_at' => now(),
            ], $extra));

            $user = $fresh->user;

            // 1. Deposit lands in A-WALLET (capital). It stays here until the
            //    member clicks "Fund" to lock it into a 200% package.
            $this->wallets->credit($user, 'A', $fresh->amount, 'deposit', $fresh, [], 'Deposit approved');

            // 2. Fund aggregate (drives rank)
            $user->increment('total_fund', $fresh->amount);

            $deposit->setRawAttributes($fresh->getAttributes());
        });

        // 3. Re-evaluate ranks across the network (total_fund changed)
        $this->ranks->updateAll();
    }

    public function reject(Deposit $deposit, User $admin, ?string $note = null): Deposit
    {
        // Reject any not-yet-credited state (manual pending, or auto verifying/review).
        if (in_array($deposit->status, ['pending', 'verifying', 'review'], true)) {
            $deposit->update([
                'status'      => 'rejected',
                'approved_by' => $admin->id,
                'approved_at' => now(),
                'note'        => $note,
            ]);
        }
        return $deposit;
    }
}
