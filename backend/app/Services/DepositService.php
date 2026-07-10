<?php

namespace App\Services;

use App\Models\Deposit;
use App\Models\User;
use App\Services\SettingsService;
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
        $deposit->refresh();

        // Notify the member their auto-deposit was credited (same email the admin
        // approval sends). Failure must not block the credit.
        if ($deposit->status === 'approved') {
            try {
                $u = $deposit->user;
                app(\App\Services\MailService::class)->sendDepositApproved(
                    $u->email, $u->name ?: $u->username, number_format((float) $deposit->amount, 2)
                );
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Auto-deposit email failed: ' . $e->getMessage());
            }
        }

        return $deposit;
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

            // 3. Deposit bonus promo — extra % of the deposit into A-WALLET when a
            //    promo is running and this deposit was made within the window.
            //    Only reaches here for REAL deposits (form + auto), never admin
            //    adjust / LD transfer (those don't go through credit()).
            $bonus = $this->depositBonus($fresh);
            if ($bonus !== null && bccomp($bonus, '0', 8) > 0) {
                $this->wallets->credit($user, 'A', $bonus, 'deposit_bonus', $fresh, ['percent' => (float) app(SettingsService::class)->get('deposit_bonus_percent', 0)], 'Deposit bonus (promo)');
            }

            $deposit->setRawAttributes($fresh->getAttributes());
        });

        // 3. Re-evaluate ranks across the network (total_fund changed)
        $this->ranks->updateAll();
    }

    /**
     * The promo bonus (USDT, 8dp) for a deposit, or null when no promo applies.
     * Eligible when the promo is enabled, the percent > 0, and the deposit was
     * MADE (created_at) within the [start, end] window (end is inclusive of the
     * whole day). Timezone is the app timezone.
     */
    protected function depositBonus(Deposit $deposit): ?string
    {
        $s = app(SettingsService::class);
        if (! $s->get('deposit_bonus_enabled')) {
            return null;
        }
        $pct = (float) $s->get('deposit_bonus_percent', 0);
        if ($pct <= 0) {
            return null;
        }

        $tz    = config('app.timezone');
        $when  = ($deposit->created_at ?? now())->copy()->setTimezone($tz);
        $start = $s->get('deposit_bonus_start');
        $end   = $s->get('deposit_bonus_end');

        if ($start && $when->lt(\Illuminate\Support\Carbon::parse($start, $tz)->startOfDay())) {
            return null;
        }
        if ($end && $when->gt(\Illuminate\Support\Carbon::parse($end, $tz)->endOfDay())) {
            return null;
        }

        return bcmul((string) $deposit->amount, (string) ($pct / 100), 8);
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
