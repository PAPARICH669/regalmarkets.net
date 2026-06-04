"use client";

import { useCallback, useEffect, useState } from "react";
import { TrendingUp, Gift, Layers, Coins, Network as NetIcon } from "lucide-react";
import StatCard from "@/components/StatCard";
import api from "@/lib/api";
import { usdt, shortDate } from "@/lib/format";

interface DailyRow { date: string; roi: number; sponsor: number; matching: number; total: number; }
interface LevelRow { level: number; members: number; sales: number; invested: number; }
interface Report {
  range_days: number;
  daily: DailyRow[];
  totals: { roi: number; sponsor: number; matching: number; earnings: number };
  group_sales_by_level: LevelRow[];
  total_group_sales: number;
  total_group_members: number;
}

export default function ReportsPage() {
  const [days, setDays] = useState(30);
  const [r, setR] = useState<Report | null>(null);
  const [loading, setLoading] = useState(true);

  const load = useCallback(() => {
    setLoading(true);
    api.get(`/reports/daily?days=${days}`).then((res) => setR(res.data)).finally(() => setLoading(false));
  }, [days]);
  useEffect(() => { load(); }, [load]);

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between flex-wrap gap-3">
        <div>
          <h1 className="text-2xl font-bold">Reports</h1>
          <p className="text-muted text-sm">Daily ROI, bonuses, and group sales breakdown.</p>
        </div>
        <select className="input-field w-auto" value={days} onChange={(e) => setDays(Number(e.target.value))}>
          <option value={7}>Last 7 days</option>
          <option value={30}>Last 30 days</option>
          <option value={90}>Last 90 days</option>
        </select>
      </div>

      {/* Period summary */}
      <div className="grid sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <StatCard label={`ROI (${days}d)`} value={usdt(r?.totals.roi ?? 0)} icon={<TrendingUp size={18} />} accent />
        <StatCard label={`Sponsor Bonus (${days}d)`} value={usdt(r?.totals.sponsor ?? 0)} icon={<Gift size={18} />} />
        <StatCard label={`Matching Bonus (${days}d)`} value={usdt(r?.totals.matching ?? 0)} icon={<Layers size={18} />} />
        <StatCard label={`Total Earnings (${days}d)`} value={usdt(r?.totals.earnings ?? 0)} icon={<Coins size={18} />} accent />
      </div>

      {/* Group sales by level */}
      <div className="glass p-5">
        <div className="flex items-center justify-between flex-wrap gap-2 mb-4">
          <h3 className="font-semibold flex items-center gap-2"><NetIcon size={18} className="text-gold-light" /> Group Sales by Level</h3>
          <div className="text-right">
            <p className="text-xs text-muted">Total Group Sales</p>
            <p className="text-lg font-bold gold-text">{usdt(r?.total_group_sales ?? 0)} <span className="text-xs text-muted font-normal">· {r?.total_group_members ?? 0} members</span></p>
          </div>
        </div>
        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead className="text-gold-light text-left"><tr><th className="py-2">Level</th><th>Members</th><th>Group Sales</th><th>Invested</th></tr></thead>
            <tbody>
              {(r?.group_sales_by_level ?? []).map((l) => (
                <tr key={l.level} className="border-t border-[var(--line)]">
                  <td className="py-2">Level {l.level}</td>
                  <td>{l.members}</td>
                  <td className="gold-text">{usdt(l.sales)}</td>
                  <td className="text-muted">{usdt(l.invested)}</td>
                </tr>
              ))}
              {(!r || r.group_sales_by_level.length === 0) && (
                <tr><td colSpan={4} className="py-4 text-center text-muted">No downline sales yet.</td></tr>
              )}
            </tbody>
            {r && r.group_sales_by_level.length > 0 && (
              <tfoot>
                <tr className="border-t border-[var(--line)] font-semibold">
                  <td className="py-2">Total</td>
                  <td>{r.total_group_members}</td>
                  <td className="gold-text">{usdt(r.total_group_sales)}</td>
                  <td></td>
                </tr>
              </tfoot>
            )}
          </table>
        </div>
      </div>

      {/* Daily breakdown */}
      <div className="glass p-5">
        <h3 className="font-semibold mb-4">Daily Breakdown</h3>
        <div className="overflow-x-auto max-h-[480px] overflow-y-auto">
          <table className="w-full text-sm">
            <thead className="text-gold-light text-left sticky top-0 bg-[var(--surface)]">
              <tr><th className="py-2">Date</th><th>Daily ROI</th><th>Sponsor</th><th>Matching</th><th>Total</th></tr>
            </thead>
            <tbody>
              {(r?.daily ?? []).map((row) => {
                const empty = row.total === 0;
                return (
                  <tr key={row.date} className={`border-t border-[var(--line)] ${empty ? "opacity-50" : ""}`}>
                    <td className="py-2">{shortDate(row.date).split(",")[0]}</td>
                    <td>{usdt(row.roi)}</td>
                    <td>{usdt(row.sponsor)}</td>
                    <td>{usdt(row.matching)}</td>
                    <td className="gold-text font-medium">{usdt(row.total)}</td>
                  </tr>
                );
              })}
              {loading && <tr><td colSpan={5} className="py-4 text-center text-muted">Loading…</td></tr>}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  );
}
