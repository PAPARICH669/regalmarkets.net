"use client";

import { useCallback, useEffect, useState } from "react";
import api, { apiError } from "@/lib/api";
import { usdt, shortDate } from "@/lib/format";
import StatusPill from "@/components/StatusPill";

interface Withdrawal { id: number; amount: string; fee: string; net_amount: string; wallet_address: string; status: string; created_at: string; user?: { username: string; email: string }; }

export default function AdminWithdrawals() {
  const [items, setItems] = useState<Withdrawal[]>([]);
  const [filter, setFilter] = useState("pending");
  const [error, setError] = useState("");

  const load = useCallback(() => {
    api.get(`/admin/withdrawals${filter ? `?status=${filter}` : ""}`).then((r) => setItems(r.data.data));
  }, [filter]);
  useEffect(() => { load(); }, [load]);

  async function approve(id: number) {
    setError("");
    const txid = window.prompt("Enter payout TXID (optional):") ?? "";
    try { await api.post(`/admin/withdrawals/${id}/approve`, { txid }); load(); }
    catch (err) { setError(apiError(err)); }
  }
  async function reject(id: number) {
    setError("");
    try { await api.post(`/admin/withdrawals/${id}/reject`, { note: "Rejected by admin" }); load(); }
    catch (err) { setError(apiError(err)); }
  }

  return (
    <div className="space-y-5">
      <div className="flex items-center justify-between flex-wrap gap-3">
        <h1 className="text-2xl font-bold">Withdrawals</h1>
        <select className="input-field w-auto" value={filter} onChange={(e) => setFilter(e.target.value)}>
          <option value="">All</option><option value="pending">Pending</option><option value="approved">Approved</option><option value="rejected">Rejected</option>
        </select>
      </div>
      {error && <div className="text-sm text-red-400 bg-red-500/10 border border-red-500/30 rounded-lg px-4 py-3">{error}</div>}
      <div className="glass overflow-x-auto">
        <table className="w-full text-sm">
          <thead className="bg-white/5 text-gold-light text-left"><tr><th className="px-4 py-3">Member</th><th>Amount</th><th>Net</th><th>Address</th><th>Status</th><th>Action</th></tr></thead>
          <tbody>
            {items.map((w) => (
              <tr key={w.id} className="border-t border-[var(--line)]">
                <td className="px-4 py-3">{w.user?.username}<div className="text-xs text-muted">{shortDate(w.created_at)}</div></td>
                <td>{usdt(w.amount)}</td>
                <td>{usdt(w.net_amount)}</td>
                <td className="text-xs text-muted truncate max-w-[140px]">{w.wallet_address}</td>
                <td><StatusPill status={w.status} /></td>
                <td>
                  {w.status === "pending" ? (
                    <div className="flex gap-2">
                      <button onClick={() => approve(w.id)} className="btn-gold px-3 py-1 text-xs">Approve</button>
                      <button onClick={() => reject(w.id)} className="btn-ghost px-3 py-1 text-xs text-red-400">Reject</button>
                    </div>
                  ) : <span className="text-xs text-muted">—</span>}
                </td>
              </tr>
            ))}
            {items.length === 0 && <tr><td colSpan={6} className="py-6 text-center text-muted">No withdrawals.</td></tr>}
          </tbody>
        </table>
      </div>
    </div>
  );
}
