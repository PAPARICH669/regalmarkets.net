<?php

namespace App\Services;

use App\Models\InvestmentPackage;
use App\Models\SponsorReward;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Monthly Top-5 Sponsor reward. Members are ranked by the NEW investment their
 * DIRECT (level-1) downlines funded during the month. The top 5 earn a fixed
 * USDT reward (config regal.top_sponsor_rewards). Rewards are created as
 * "pending" for an admin to settle; settling credits E-WALLET + emails the user.
 */
class SponsorRewardService
{
    public function __construct(protected WalletService $wallets) {}

    /** Default period = the PREVIOUS calendar month, 'YYYY-MM' (Asia/Kuala_Lumpur). */
    public function previousPeriod(): string
    {
        return Carbon::now(config('app.timezone'))->subMonthNoOverflow()->format('Y-m');
    }

    /**
     * Generate pending rewards for a period (idempotent — does nothing if the
     * period already has rows). Returns the reward rows for that period.
     */
    public function generate(string $period): array
    {
        if (SponsorReward::where('period', $period)->exists()) {
            return ['created' => 0, 'period' => $period, 'note' => 'already generated'];
        }

        [$start, $end] = $this->bounds($period);

        // New invest per member during the month (from package activations).
        $investByUser = InvestmentPackage::whereBetween('activated_at', [$start, $end])
            ->selectRaw('user_id, SUM(principal) as inv')
            ->groupBy('user_id')
            ->pluck('inv', 'user_id');

        // Attribute each member's monthly invest to their DIRECT sponsor.
        $sponsorOf = User::pluck('sponsor_id', 'id');
        $score = [];
        foreach ($investByUser as $uid => $inv) {
            $sp = (int) ($sponsorOf[$uid] ?? 0);
            if ($sp && (float) $inv > 0) {
                $score[$sp] = ($score[$sp] ?? 0) + (float) $inv;
            }
        }

        // Exclude admin/staff from the ranking.
        foreach (User::where('is_admin', true)->orWhere('is_staff', true)->pluck('id') as $id) {
            unset($score[$id]);
        }

        arsort($score);
        $top = array_slice($score, 0, 5, true);
        $amounts = config('regal.top_sponsor_rewards', [50, 20, 15, 10, 5]);

        $created = 0;
        $pos = 0;
        foreach ($top as $uid => $sc) {
            $pos++;
            if ($sc <= 0) {
                break;
            }
            SponsorReward::create([
                'period'   => $period,
                'user_id'  => $uid,
                'position' => $pos,
                'score'    => $sc,
                'amount'   => $amounts[$pos - 1] ?? 0,
                'status'   => 'pending',
            ]);
            $created++;
        }

        return ['created' => $created, 'period' => $period];
    }

    /** Settle one reward: credit E-WALLET + email the member. */
    public function settle(SponsorReward $reward, User $admin): void
    {
        if ($reward->status === 'paid') {
            return;
        }
        $user = $reward->user;
        if (! $user) {
            return;
        }

        DB::transaction(function () use ($reward, $admin, $user) {
            $this->wallets->credit(
                $user, 'E', $reward->amount, 'sponsor_reward', $reward,
                ['period' => $reward->period, 'position' => $reward->position],
                "Top {$reward->position} Sponsor reward for {$reward->period}"
            );
            $reward->update(['status' => 'paid', 'paid_by' => $admin->id, 'paid_at' => now()]);
        });

        try {
            app(MailService::class)->sendSponsorReward(
                $user->email, $user->username, (int) $reward->position,
                (string) $reward->amount, $reward->period
            );
        } catch (\Throwable $e) {
            Log::warning('Sponsor-reward email failed: ' . $e->getMessage());
        }
    }

    /** @return array{0:Carbon,1:Carbon} */
    protected function bounds(string $period): array
    {
        $start = Carbon::createFromFormat('Y-m', $period, config('app.timezone'))->startOfMonth();
        $end   = (clone $start)->endOfMonth();
        return [$start, $end];
    }
}
