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

        DB::transaction(function () use ($deposit, $admin) {
            $deposit->update([
                'status'      => 'approved',
                'approved_by' => $admin->id,
                'approved_at' => now(),
            ]);

            $user = $deposit->user;

            // 1. Deposit lands in A-WALLET (capital). It stays here until the
            //    member clicks "Fund" to lock it into a 200% package.
            $this->wallets->credit($user, 'A', $deposit->amount, 'deposit', $deposit, [], 'Deposit approved');

            // 2. Fund aggregate (drives rank)
            $user->increment('total_fund', $deposit->amount);
        });

        // 3. Re-evaluate ranks across the network (total_fund changed)
        $this->ranks->updateAll();

        return $deposit->refresh();
    }

    public function reject(Deposit $deposit, User $admin, ?string $note = null): Deposit
    {
        if ($deposit->status === 'pending') {
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
