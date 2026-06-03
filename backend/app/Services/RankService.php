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

        // Lightweight in-memory snapshot
        $users = User::members()->get(['id', 'sponsor_id', 'total_fund', 'rank_id']);

        $level    = [];   // userId => current rank level (default 1)
        $fund     = [];
        $children = [];   // sponsorId => [childIds]
        $rankIdToLevel = $ranksByName->mapWithKeys(fn ($r) => [$r->id => $r->level])->toArray();

        foreach ($users as $u) {
            $level[$u->id] = $u->rank_id ? ($rankIdToLevel[$u->rank_id] ?? 1) : 1;
            $fund[$u->id]  = (float) $u->total_fund;
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
            }
        }

        return ['changed' => $changed];
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

        // SENIOR/TL/GL: count legs that produced the required rank
        if ($rank->produce_rank && $rank->produce_count) {
            $targetLevel = $this->levelOfName($rank->produce_rank);
            $legs = 0;
            foreach ($directs as $d) {
                if ($this->subtreeHasLevel($d, $targetLevel, $level, $children)) {
                    $legs++;
                }
            }
            return $legs >= $rank->produce_count;
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
