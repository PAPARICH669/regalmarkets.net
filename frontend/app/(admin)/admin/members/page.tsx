"use client";

import { useCallback, useEffect, useState } from "react";
import api, { apiError } from "@/lib/api";
import { usdt } from "@/lib/format";
import RankBadge from "@/components/RankBadge";

interface Member {
  id: number; username: string; email: string; total_fund: string; total_invested: string;
  is_frozen: boolean; referrals_count: number; rank?: { id: number; name: string };
}
interface Rank { id: number; name: string; }

export default function AdminMembers() {
  const [items, setItems] = useState<Member[]>([]);
  const [ranks, setRanks] = useState<Rank[]>([]);
  const [search, setSearch] = useState("");
  const [error, setError] = useState("");

  const load = useCallback(() => {
    api.get(`/admin/members${search ? `?search=${encodeURIComponent(search)}` : ""}`).then((r) => setItems(r.data.data));
  }, [search]);
  useEffect(() => { load(); api.get("/ranks").then((r) => setRanks(r.data)); }, [load]);

  async function freeze(id: number) {
    try { await api.post(`/admin/members/${id}/freeze`); load(); } catch (e) { setError(apiError(e)); }
  }
  async function adjust(id: number) {
    const type = window.prompt("Wallet (A or E):", "E"); if (!type) return;
    const direction = window.prompt("credit or debit:", "credit"); if (!direction) return;
    const amount = window.prompt("Amount:"); if (!amount) return;
    try { await api.post(`/admin/members/${id}/adjust-wallet`, { type, direction, amount }); load(); }
    catch (e) { setError(apiError(e)); }
  }
  async function setRank(id: number, rank_id: number) {
    try { await api.post(`/admin/members/${id}/rank`, { rank_id }); load(); } catch (e) { setError(apiError(e)); }
  }

  return (
    <div className="space-y-5">
      <div className="flex items-center justify-between flex-wrap gap-3">
        <h1 className="text-2xl font-bold">Members</h1>
        <input className="input-field w-64" placeholder="Search username/email…" value={search} onChange={(e) => setSearch(e.target.value)} />
      </div>
      {error && <div className="text-sm text-red-400 bg-red-500/10 border border-red-500/30 rounded-lg px-4 py-3">{error}</div>}
      <div className="glass overflow-x-auto">
        <table className="w-full text-sm">
          <thead className="bg-white/5 text-gold-light text-left"><tr><th className="px-4 py-3">Member</th><th>Rank</th><th>Fund</th><th>Invested</th><th>Directs</th><th>Status</th><th>Actions</th></tr></thead>
          <tbody>
            {items.map((m) => (
              <tr key={m.id} className="border-t border-[var(--line)]">
                <td className="px-4 py-3">{m.username}<div className="text-xs text-muted">{m.email}</div></td>
                <td>{m.rank ? <RankBadge rank={m.rank.name} /> : "—"}</td>
                <td>{usdt(m.total_fund)}</td>
                <td>{usdt(m.total_invested)}</td>
                <td>{m.referrals_count}</td>
                <td>{m.is_frozen ? <span className="text-xs text-red-400">Frozen</span> : <span className="text-xs text-green-400">Active</span>}</td>
                <td>
                  <div className="flex flex-wrap gap-1.5">
                    <button onClick={() => freeze(m.id)} className="btn-ghost px-2 py-1 text-xs">{m.is_frozen ? "Unfreeze" : "Freeze"}</button>
                    <button onClick={() => adjust(m.id)} className="btn-ghost px-2 py-1 text-xs">Adjust</button>
                    <select className="bg-[var(--surface)] border border-[var(--line)] rounded px-1 py-1 text-xs"
                      value={m.rank?.id ?? ""} onChange={(e) => setRank(m.id, Number(e.target.value))}>
                      {ranks.map((r) => <option key={r.id} value={r.id}>{r.name}</option>)}
                    </select>
                  </div>
                </td>
              </tr>
            ))}
            {items.length === 0 && <tr><td colSpan={7} className="py-6 text-center text-muted">No members.</td></tr>}
          </tbody>
        </table>
      </div>
    </div>
  );
}
