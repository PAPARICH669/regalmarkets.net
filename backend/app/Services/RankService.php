<?php

namespace App\Services;

use App\Models\Rank;
use App\Models\RankHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Rank engine. Recomputes member ranks from fund + downline production.
 *
 * Requirements (see config/regal.php + docs/BUSINESS_LOGIC.md):
 *   FAN          total_fund >= 100  AND  >= 3 directs each with deposit >= 100
 *   SENIOR       produce >= 3 FAN legs                 (min_fund 100)
 *   TEAM LEADER  total_fund >= 500   AND produce >= 3 SENIOR legs
 *   GROUP LEADER total_fund >= 5000  AND produce >= 3 TEAM LEADER legs
 *
 * A "leg" qualifies for produce-rank X when a direct downline's subtree (incl.
 * itself) contains at least one member of rank level >= X. The engine promotes
 * only (no auto-demotion) and iterates to a fixpoint since levels only rise.
 */
class RankService
{
    /** @return array{changed:int} */
    public function updateAll(): array
    {
        $ranksByLevel = Rank::orderBy('level')->get()->keyBy('level');
        $ranksByName  = Rank::all()->keyBy('name');

        // Lightweight in-memory snapshot.
        // Rank eligibility is measured by TOTAL_INVESTED (lifetime capital the
        // member has funded into packages). We deliberately do NOT use total_fund
        // here: total_fund only counts approved *deposits* and drops back to 0 when
        // a 200% package completes, which would wrongly strip qualified members
        // (and misses admin-credited / funded capital). total_invested never drops.
        $users = User::members()->get(['id', 'sponsor_id', 'total_invested', 'rank_id']);

        $level    = [];   // userId => current rank level (default 1)
        $fund     = [];   // userId => total_invested (the rank-qualifying amount)
        $children = [];   // sponsorId => [childIds]
        $rankIdToLevel = $ranksByName->mapWithKeys(fn ($r) => [$r->id => $r->level])->toArray();

        foreach ($users as $u) {
            $level[$u->id] = $u->rank_id ? ($rankIdToLevel[$u->rank_id] ?? 1) : 1;
            $fund[$u->id]  = (float) $u->total_invested;
            $children[$u->sponsor_id][] = $u->id;
        }

        // Fixpoint: recompute until no level rises (max 6 passes covers 5 tiers).
        for ($pass = 0; $pass < 6; $pass++) {
            $changedThisPass = false;
            foreach ($users as $u) {
                $newLevel = $this->evaluate($u->id, $level, $fund, $children, $ranksByLevel);
                if ($newLevel > $level[$u->id]) {
                    $level[$u->id] = $newLevel;
                    $changedThisPass = true;
                }
            }
            if (! $changedThisPass) {
                break;
            }
        }

        // Persist changes
        $changed = 0;
        foreach ($users as $u) {
            $targetLevel = $level[$u->id];
            $currentLevel = $u->rank_id ? ($rankIdToLevel[$u->rank_id] ?? 1) : 1;
            if ($targetLevel !== $currentLevel || $u->rank_id === null) {
                $targetRank = $ranksByLevel[$targetLevel];
                DB::transaction(function () use ($u, $targetRank) {
                    $from = $u->rank_id;
                    User::whereKey($u->id)->update(['rank_id' => $targetRank->id]);
                    RankHistory::create([
                        'user_id'      => $u->id,
                        'from_rank_id' => $from,
                        'to_rank_id'   => $targetRank->id,
                        'reason'       => 'auto rank engine',
                    ]);
                });
                $changed++;

                // Rank Rewards Campaign — pay a one-time USDT reward when the rank
                // actually RISES (forward-only; never on the initial USER rank).
                if ($targetLevel > $currentLevel) {
                    $this->payRankReward($u->id, $targetRank);
                }
            }
        }

        return ['changed' => $changed];
    }

    /**
     * Does this member GENUINELY meet the requirements of their displayed rank
     * (own total_invested + direct-leg production)? Used by the matching-bonus
     * override so a manually-boosted rank does not earn the higher matching % until
     * it is actually earned. USER (level 1) always qualifies.
     */
    public function qualifiesForOwnRank(User $user): bool
    {
        $rank = $user->rank;
        if (! $rank || (int) $rank->level <= 1) {
            return true;
        }
        $rankIdToLevel = Rank::all()->pluck('level', 'id')->toArray();
        $directs = User::where('sponsor_id', $user->id)->get(['id', 'rank_id', 'total_invested']);
        $level = [];
        $fund  = [];
        foreach ($directs as $d) {
            $level[$d->id] = $d->rank_id ? ($rankIdToLevel[$d->rank_id] ?? 1) : 1;
            $fund[$d->id]  = (float) $d->total_invested;
        }
        return $this->meets($rank, (float) $user->total_invested, $directs->pluck('id')->all(), $level, [], $fund);
    }

    /**
     * Rank Rewards Campaign: credit a one-time USDT reward to the member's E-WALLET
     * when they are promoted to a rank that has a configured reward. Idempotent
     * (one payout per member per rank) and never breaks rank processing on error.
     */
    protected function payRankReward(int $userId, Rank $rank): void
    {
        $settings = app(SettingsService::class);
        if (! $settings->get('rank_rewards_enabled')) {
            return;
        }
        $rewards = (array) ($settings->get('rank_rewards') ?: []);
        $amount  = (float) ($rewards[$rank->name] ?? 0);
        if ($amount <= 0) {
            return;
        }
        // Optional campaign window (Y-m-d, inclusive).
        $tz    = config('app.timezone');
        $now   = \Carbon\Carbon::now($tz);
        $start = $settings->get('rank_rewards_start');
        $end   = $settings->get('rank_rewards_end');
        if ($start && $now->lt(\Carbon\Carbon::parse($start, $tz)->startOfDay())) {
            return;
        }
        if ($end && $now->gt(\Carbon\Carbon::parse($end, $tz)->endOfDay())) {
            return;
        }
        // One reward per member per rank.
        if (\App\Models\RankRewardPayout::where('user_id', $userId)->where('rank_id', $rank->id)->exists()) {
            return;
        }
        $user = User::find($userId);
        if (! $user) {
            return;
        }
        try {
            DB::transaction(function () use ($user, $rank, $amount) {
                \App\Models\RankRewardPayout::create([
                    'user_id' => $user->id,
                    'rank_id' => $rank->id,
                    'amount'  => $amount,
                    'paid_at' => now(),
                ]);
                app(WalletService::class)->credit(
                    $user, 'E', $amount, 'rank_reward', null,
                    ['rank' => $rank->name, 'rank_id' => $rank->id],
                    'Rank reward: ' . $rank->name
                );
            });
            app(TelegramService::class)->notify('🏆 Rank Reward Paid', [
                'User'   => '@' . $user->username,
                'Rank'   => $rank->name,
                'Reward' => number_format($amount, 2) . ' USDT → E-Wallet',
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Rank reward failed for user ' . $userId . ': ' . $e->getMessage());
        }
    }

    /** Highest rank level a user qualifies for given current snapshot. */
    protected function evaluate(int $userId, array $level, array $fund, array $children, $ranksByLevel): int
    {
        $directs = $children[$userId] ?? [];
        $userFund = $fund[$userId] ?? 0;
        $result = 1; // USER

        foreach ($ranksByLevel as $lvl => $rank) {
            if ($lvl === 1) {
                continue;
            }
            if ($this->meets($rank, $userFund, $directs, $level, $children, $fund)) {
                $result = max($result, (int) $lvl);
            }
        }

        return $result;
    }

    protected function meets(Rank $rank, float $userFund, array $directs, array $level, array $children, array $fund): bool
    {
        if ($userFund < (float) $rank->min_fund) {
            return false;
        }

        // FAN: count directs whose own fund >= direct_min_deposit
        if ($rank->directs_required) {
            $qualifying = 0;
            foreach ($directs as $d) {
                if (($fund[$d] ?? 0) >= (float) $rank->direct_min_deposit) {
                    $qualifying++;
                }
            }
            return $qualifying >= $rank->directs_required;
        }

        // SENIOR/TL/GL: count DIRECT (level-1) referrals whose rank is at least the
        // required rank. e.g. SENIOR needs >= 3 direct FAN-or-higher.
        if ($rank->produce_rank && $rank->produce_count) {
            $targetLevel = $this->levelOfName($rank->produce_rank);
            $qualifying = 0;
            foreach ($directs as $d) {
                if (($level[$d] ?? 1) >= $targetLevel) {
                    $qualifying++;
                }
            }
            return $qualifying >= $rank->produce_count;
        }

        return true;
    }

    protected function subtreeHasLevel(int $root, int $targetLevel, array $level, array $children): bool
    {
        $stack = [$root];
        while ($stack) {
            $node = array_pop($stack);
            if (($level[$node] ?? 1) >= $targetLevel) {
                return true;
            }
            foreach ($children[$node] ?? [] as $c) {
                $stack[] = $c;
            }
        }
        return false;
    }

    protected function levelOfName(string $name): int
    {
        return match ($name) {
            'USER' => 1, 'FAN' => 2, 'SENIOR' => 3, 'TEAM LEADER' => 4, 'GROUP LEADER' => 5,
            default => 1,
        };
    }
}
