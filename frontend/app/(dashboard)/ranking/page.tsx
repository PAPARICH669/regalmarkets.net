"use client";

import { useEffect, useState } from "react";
import api from "@/lib/api";
import { useAuth } from "@/lib/auth";
import RankBadge from "@/components/RankBadge";
import { usdt, shortDate } from "@/lib/format";

interface Rank { id: number; name: string; level: number; match_percent: string; min_fund: string; }
interface MatchLog { id: number; from_user?: { username: string }; upline_rank: string; applied_percent: string; amount: string; created_at: string; }
interface SponsorLog { id: number; from_user?: { username: string }; level: number; percent: string; amount: string; created_at: string; }

export default function RankingPage() {
  const user = useAuth((s) => s.user);
  const [ranks, setRanks] = useState<Rank[]>([]);
  const [matching, setMatching] = useState<MatchLog[]>([]);
  const [sponsor, setSponsor] = useState<SponsorLog[]>([]);

  useEffect(() => {
    api.get("/ranks").then((r) => setRanks(r.data));
    api.get("/logs/matching").then((r) => setMatching(r.data.data));
    api.get("/logs/sponsor").then((r) => setSponsor(r.data.data));
  }, []);

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between flex-wrap gap-3">
        <h1 className="text-2xl font-bold">Ranking & Bonuses</h1>
        {user && <div className="flex items-center gap-2"><span className="text-sm text-muted">Your rank:</span><RankBadge rank={user.rank} /></div>}
      </div>

      <div className="glass overflow-hidden">
        <table className="w-full text-sm">
          <thead className="bg-white/5 text-gold-light text-left"><tr><th className="px-5 py-3">Rank</th><th>Match %</th><th>Min Fund</th></tr></thead>
          <tbody>
            {ranks.map((r) => (
              <tr key={r.id} className={`border-t border-[var(--line)] ${user?.rank === r.name ? "bg-[rgba(201,162,39,0.06)]" : ""}`}>
                <td className="px-5 py-3"><RankBadge rank={r.name} /></td>
                <td className="gold-text font-bold">{Number(r.match_percent)}%</td>
                <td className="text-muted">{usdt(r.min_fund)}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      <div className="grid lg:grid-cols-2 gap-6">
        <div className="glass p-5">
          <h3 className="font-semibold mb-4">Matching Bonus Logs</h3>
          <div className="overflow-x-auto max-h-80 overflow-y-auto">
            <table className="w-full text-sm">
              <thead className="text-gold-light text-left sticky top-0 bg-[var(--surface)]"><tr><th className="py-2">From</th><th>Rank</th><th>%</th><th>Amount</th></tr></thead>
              <tbody>
                {matching.map((m) => (
                  <tr key={m.id} className="border-t border-[var(--line)]">
                    <td className="py-2">{m.from_user?.username ?? "—"}</td>
                    <td className="text-xs text-muted">{m.upline_rank}</td>
                    <td>{Number(m.applied_percent)}%</td>
                    <td className="gold-text">{usdt(m.amount)}</td>
                  </tr>
                ))}
                {matching.length === 0 && <tr><td colSpan={4} className="py-4 text-muted text-center">No matching bonuses yet.</td></tr>}
              </tbody>
            </table>
          </div>
        </div>

        <div className="glass p-5">
          <h3 className="font-semibold mb-4">Sponsor Bonus Logs</h3>
          <div className="overflow-x-auto max-h-80 overflow-y-auto">
            <table className="w-full text-sm">
              <thead className="text-gold-light text-left sticky top-0 bg-[var(--surface)]"><tr><th className="py-2">From</th><th>Level</th><th>%</th><th>Amount</th></tr></thead>
              <tbody>
                {sponsor.map((s) => (
                  <tr key={s.id} className="border-t border-[var(--line)]">
                    <td className="py-2">{s.from_user?.username ?? "—"}</td>
                    <td className="text-xs text-muted">L{s.level}</td>
                    <td>{Number(s.percent)}%</td>
                    <td className="gold-text">{usdt(s.amount)}</td>
                  </tr>
                ))}
                {sponsor.length === 0 && <tr><td colSpan={4} className="py-4 text-muted text-center">No sponsor bonuses yet.</td></tr>}
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  );
}
