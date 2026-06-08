"use client";

import { useEffect, useState } from "react";
import api from "@/lib/api";
import NetworkTree, { TreeNode } from "@/components/NetworkTree";
import StatCard from "@/components/StatCard";
import { usdt } from "@/lib/format";
import { Users, Network as NetIcon, DollarSign } from "lucide-react";

interface Stats { total_team: number; direct_referrals: number; team_sales: number; team_invested: number; by_level: Record<string, number>; rank_counts: Record<string, number>; }

export default function NetworkPage() {
  const [tree, setTree] = useState<TreeNode | null>(null);
  const [stats, setStats] = useState<Stats | null>(null);

  useEffect(() => {
    api.get("/network/tree?depth=12").then((r) => setTree(r.data));
    api.get("/network/stats").then((r) => setStats(r.data));
  }, []);

  return (
    <div className="space-y-6">
      <h1 className="text-2xl font-bold">Team Network</h1>

      {stats && (
        <div className="grid sm:grid-cols-2 lg:grid-cols-4 gap-4">
          <StatCard label="Direct Referrals" value={stats.direct_referrals} icon={<Users size={18} />} />
          <StatCard label="Total Team" value={stats.total_team} icon={<NetIcon size={18} />} accent />
          <StatCard label="Team Sales" value={usdt(stats.team_sales)} icon={<DollarSign size={18} />} />
          <StatCard label="Team Invested" value={usdt(stats.team_invested)} icon={<DollarSign size={18} />} />
        </div>
      )}

      {stats && (
        <div className="glass p-5">
          <h3 className="font-semibold mb-4">Rank Distribution</h3>
          <div className="flex flex-wrap gap-3">
            {Object.entries(stats.rank_counts || {}).map(([r, c]) => <div key={r} className="rank-badge">{r}: {c}</div>)}
            {Object.keys(stats.rank_counts || {}).length === 0 && <p className="text-muted text-sm">No downline yet.</p>}
          </div>
        </div>
      )}

      <div>
        <h3 className="font-semibold mb-3">Genealogy / Sponsor Tree</h3>
        {tree ? <NetworkTree root={tree} /> : <p className="text-muted">Loading tree…</p>}
      </div>
    </div>
  );
}
