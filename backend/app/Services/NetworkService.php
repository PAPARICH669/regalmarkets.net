<?php

namespace App\Services;

use App\Models\Rank;
use App\Models\User;

/**
 * Genealogy / sponsor tree + downline statistics.
 *
 * PERF: the whole member set is small, so every traversal loads ALL users once
 * (a single query) into a sponsor_id => children map and walks it in memory —
 * instead of one `where sponsor_id = ?` query per node (which made the member
 * dashboard N+1-slow, ~900ms for big downlines).
 */
class NetworkService
{
    /** [childrenBySponsorId, rankNameById] — built with two queries, cached per request. */
    protected ?array $index = null;

    protected function index(): array
    {
        if ($this->index !== null) {
            return $this->index;
        }
        $children = [];
        foreach (User::query()->get(['id', 'username', 'sponsor_id', 'rank_id', 'total_fund', 'total_invested']) as $u) {
            $children[$u->sponsor_id][] = $u;
        }
        $ranks = Rank::pluck('name', 'id')->all();
        return $this->index = [$children, $ranks];
    }

    /** Build a nested sponsor tree for a user up to $maxDepth levels. */
    public function tree(User $root, int $maxDepth = 5): array
    {
        [$children, $ranks] = $this->index();
        return $this->node($root->id, $root->username, $root->rank_id, (float) $root->total_fund, (float) $root->total_invested, $children, $ranks, $maxDepth, 0);
    }

    protected function node($id, $username, $rankId, $fund, $invested, array $children, array $ranks, int $maxDepth, int $depth): array
    {
        $data = [
            'id'             => $id,
            'username'       => $username,
            'rank'           => $ranks[$rankId] ?? 'USER',
            'total_fund'     => $fund,
            'total_invested' => $invested,
            'depth'          => $depth,
            'children'       => [],
        ];

        if ($depth < $maxDepth) {
            foreach (($children[$id] ?? []) as $c) {
                $data['children'][] = $this->node($c->id, $c->username, $c->rank_id, (float) $c->total_fund, (float) $c->total_invested, $children, $ranks, $maxDepth, $depth + 1);
            }
        }

        return $data;
    }

    /** Aggregate downline statistics: team size, sales, rank counts, per-level. */
    public function stats(User $root): array
    {
        [$children, $ranks] = $this->index();

        $byLevel = []; $rankCounts = [];
        $teamSales = 0.0; $teamInvested = 0.0; $total = 0;
        $queue = [[$root->id, 0]];

        while ($queue) {
            [$id, $level] = array_shift($queue);
            foreach (($children[$id] ?? []) as $child) {
                $lvl = $level + 1;
                $byLevel[$lvl]  = ($byLevel[$lvl] ?? 0) + 1;
                $rn = $ranks[$child->rank_id] ?? 'USER';
                $rankCounts[$rn] = ($rankCounts[$rn] ?? 0) + 1;
                $teamSales    += (float) $child->total_fund;
                $teamInvested += (float) $child->total_invested;
                $total++;
                $queue[] = [$child->id, $lvl];
            }
        }
        ksort($byLevel);

        return [
            'total_team'       => $total,
            'direct_referrals' => count($children[$root->id] ?? []),
            'team_sales'       => $teamSales,
            'team_invested'    => $teamInvested,
            'by_level'         => $byLevel,
            'rank_counts'      => $rankCounts,
        ];
    }

    /**
     * Group sales broken down by level (1 = direct downline, 2 = their downline, …).
     * Sales = sum of total_fund (approved deposits) of members at that level.
     *
     * @return array{levels: array<int, array{level:int, members:int, sales:float, invested:float}>, total_sales: float, total_members: int}
     */
    public function salesByLevel(User $root, int $maxDepth = 15): array
    {
        [$children] = $this->index();

        $levels = []; $totalSales = 0.0; $totalMembers = 0;
        $queue  = [[$root->id, 0]];

        while ($queue) {
            [$id, $level] = array_shift($queue);
            if ($level >= $maxDepth) {
                continue;
            }
            foreach (($children[$id] ?? []) as $child) {
                $lvl = $level + 1;
                $levels[$lvl] ??= ['level' => $lvl, 'members' => 0, 'sales' => 0.0, 'invested' => 0.0];
                $levels[$lvl]['members']  += 1;
                $levels[$lvl]['sales']    += (float) $child->total_fund;
                $levels[$lvl]['invested'] += (float) $child->total_invested;
                $totalSales   += (float) $child->total_fund;
                $totalMembers += 1;
                $queue[] = [$child->id, $lvl];
            }
        }
        ksort($levels);

        return [
            'levels'        => array_values($levels),
            'total_sales'   => $totalSales,
            'total_members' => $totalMembers,
        ];
    }
}
