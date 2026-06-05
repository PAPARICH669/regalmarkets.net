"use client";

import { useEffect, useState } from "react";
import {
  Wallet, TrendingUp, Gift, Layers, Landmark, Coins, Users, Copy, Check,
} from "lucide-react";
import StatCard from "@/components/StatCard";
import RankCard from "@/components/RankCard";
import api from "@/lib/api";
import { useAuth } from "@/lib/auth";
import { usdt } from "@/lib/format";

interface Dashboard {
  rank: string;
  referral_link: string;
  wallets: { A: number; E: number };
  totals: {
    total_invested: number; total_return: number; total_paid: number;
    remaining_roi: number; daily_roi: number; roi_earned: number;
    sponsor_bonus: number; matching_bonus: number;
  };
  packages: { id: number; principal: number; total_return: number; total_paid: number; progress: number; status: string }[];
  team: { total_team: number; direct_referrals: number; team_sales: number; rank_counts: Record<string, number> };
  earnings_series: { date: string; roi: number; matching: number; sponsor: number; total: number }[];
}

export default function DashboardPage() {
  const user = useAuth((s) => s.user);
  const [d, setD] = useState<Dashboard | null>(null);
  const [copied, setCopied] = useState(false);

  useEffect(() => { api.get("/dashboard").then((r) => setD(r.data)); }, []);

  if (!d) return <div className="text-muted py-10">Loading dashboard…</div>;

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold">
          Welcome back, <span className="gold-text">{user?.nickname || user?.username}</span>
        </h1>
        <p className="text-muted text-sm">
          Your investment & earnings at a glance.
        </p>
      </div>

      {/* Member / rank card */}
      <RankCard />

      {/* Wallets */}
      <div className="grid sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <StatCard label="A-WALLET (Capital)" value={usdt(d.wallets.A)} icon={<Wallet size={18} />} accent />
        <StatCard label="E-WALLET (Earnings)" value={usdt(d.wallets.E)} icon={<Coins size={18} />} accent />
        <StatCard label="Total Investment" value={usdt(d.totals.total_invested)} icon={<Landmark size={18} />} />
        <StatCard label="Daily Commission" value={usdt(d.totals.daily_roi)} icon={<TrendingUp size={18} />} />
      </div>

      <div className="grid sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <StatCard label="Remaining Commission" value={usdt(d.totals.remaining_roi)} icon={<TrendingUp size={18} />} />
        <StatCard label="Commission Earned" value={usdt(d.totals.roi_earned)} icon={<Coins size={18} />} />
        <StatCard label="Sponsor Bonus" value={usdt(d.totals.sponsor_bonus)} icon={<Gift size={18} />} />
        <StatCard label="Matching Bonus" value={usdt(d.totals.matching_bonus)} icon={<Layers size={18} />} />
      </div>

      {/* Referral */}
      <div className="glass p-5">
        <h3 className="font-semibold flex items-center gap-2"><Users size={18} className="text-gold-light" /> Your Referral Link</h3>
        <p className="text-sm text-muted mt-1">Share to grow your team.</p>
        <div className="mt-4 flex items-center gap-2 bg-black/30 rounded-lg p-3 text-xs break-all">
          <span className="flex-1">{d.referral_link}</span>
          <button onClick={() => { navigator.clipboard.writeText(d.referral_link); setCopied(true); setTimeout(() => setCopied(false), 1500); }}
            className="btn-ghost p-2 shrink-0">{copied ? <Check size={15} /> : <Copy size={15} />}</button>
        </div>
        <div className="mt-5 grid sm:grid-cols-3 gap-3 text-center">
          <div className="bg-white/5 rounded-lg p-3">
            <p className="text-xl font-bold gold-text">{d.team.direct_referrals}</p>
            <p className="text-xs text-muted">Direct Referrals</p>
          </div>
          <div className="bg-white/5 rounded-lg p-3">
            <p className="text-xl font-bold gold-text">{d.team.total_team}</p>
            <p className="text-xs text-muted">Total Team</p>
          </div>
          <div className="bg-white/5 rounded-lg p-3">
            <p className="text-lg font-bold">{usdt(d.team.team_sales)}</p>
            <p className="text-xs text-muted">Team Sales Volume</p>
          </div>
        </div>
      </div>

      {/* Packages */}
      <div className="glass p-5">
        <h3 className="font-semibold mb-4">Investment Packages — Commission Progress</h3>
        {d.packages.length === 0 && <p className="text-muted text-sm">No active packages yet. Make a deposit to get started.</p>}
        <div className="space-y-4">
          {d.packages.map((p) => (
            <div key={p.id}>
              <div className="flex justify-between text-sm mb-1">
                <span>Package #{p.id} · {usdt(p.principal)}
                  <span className={`ml-2 text-xs px-2 py-0.5 rounded-full ${p.status === "active" ? "bg-green-500/15 text-green-400" : "bg-white/10 text-muted"}`}>{p.status}</span>
                </span>
                <span className="text-muted">{usdt(p.total_paid)} / {usdt(p.total_return)}</span>
              </div>
              <div className="h-2.5 rounded-full bg-white/10 overflow-hidden">
                <div className="h-full gold-gradient" style={{ width: `${p.progress}%` }} />
              </div>
            </div>
          ))}
        </div>
      </div>

      {/* Rank counts */}
      <div className="glass p-5">
        <h3 className="font-semibold mb-4">Team Rank Counts</h3>
        <div className="flex flex-wrap gap-3">
          {Object.entries(d.team.rank_counts || {}).map(([rank, count]) => (
            <div key={rank} className="rank-badge">{rank}: {count}</div>
          ))}
          {Object.keys(d.team.rank_counts || {}).length === 0 && <p className="text-muted text-sm">No downline yet.</p>}
        </div>
      </div>
    </div>
  );
}
