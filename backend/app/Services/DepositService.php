<?php

namespace App\Services;

use App\Models\Deposit;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Deposit lifecycle. Users request (pending); admin approves → activation
 * pipeline: credit A-WALLET, lock capital into a package, pay sponsor bonuses,
 * recompute fund + rank.
 */
class DepositService
{
    public function __construct(
        protected WalletService $wallets,
        protected SponsorBonusService $sponsor,
        protected InvestmentService $investment,
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

            // 1. Deposit lands in A-WALLET
            $this->wallets->credit($user, 'A', $deposit->amount, 'deposit', $deposit, [], 'Deposit approved');

            // 2. Fund aggregate (drives rank)
            $user->increment('total_fund', $deposit->amount);

            // 3. Auto-activate investment package (locks capital out of A-WALLET)
            $this->investment->activate($user, $deposit->amount, 'deposit', $deposit);

            // 4. Sponsor bonus (instant, into uplines' E-WALLET)
            $this->sponsor->distribute($user, $deposit->amount, $deposit);
        });

        // 5. Re-evaluate ranks across the network (fund + production may have changed)
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
