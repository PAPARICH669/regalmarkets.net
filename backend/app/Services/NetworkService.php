<?php

namespace App\Services;

use App\Models\User;

/**
 * Genealogy / sponsor tree + downline statistics.
 */
class NetworkService
{
    /** Build a nested sponsor tree for a user up to $maxDepth levels. */
    public function tree(User $root, int $maxDepth = 5): array
    {
        return $this->node($root, $maxDepth, 0);
    }

    protected function node(User $user, int $maxDepth, int $depth): array
    {
        $data = [
            'id'            => $user->id,
            'username'      => $user->username,
            'rank'          => $user->rankName(),
            'total_fund'    => (float) $user->total_fund,
            'total_invested'=> (float) $user->total_invested,
            'depth'         => $depth,
            'children'      => [],
        ];

        if ($depth < $maxDepth) {
            $children = User::where('sponsor_id', $user->id)->with('rank')->get();
            foreach ($children as $child) {
                $data['children'][] = $this->node($child, $maxDepth, $depth + 1);
            }
        }

        return $data;
    }

    /** Aggregate downline statistics: team size, sales, rank counts, per-level. */
    public function stats(User $root): array
    {
        $all      = collect();
        $byLevel  = [];
        $queue    = [[$root->id, 0]];

        while ($queue) {
            [$id, $level] = array_shift($queue);
            $children = User::where('sponsor_id', $id)->with('rank')->get();
            foreach ($children as $child) {
                $all->push($child);
                $byLevel[$level + 1] = ($byLevel[$level + 1] ?? 0) + 1;
                $queue[] = [$child->id, $level + 1];
            }
        }

        $rankCounts = $all->groupBy(fn ($u) => $u->rankName())->map->count();

        return [
            'total_team'      => $all->count(),
            'direct_referrals'=> User::where('sponsor_id', $root->id)->count(),
            'team_sales'      => (float) $all->sum('total_fund'),
            'team_invested'   => (float) $all->sum('total_invested'),
            'by_level'        => $byLevel,
            'rank_counts'     => $rankCounts,
        ];
    }
}
