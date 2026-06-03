"use client";

import { useCallback, useEffect, useState } from "react";
import api, { apiError } from "@/lib/api";
import { usdt, shortDate } from "@/lib/format";
import StatusPill from "@/components/StatusPill";

interface Deposit { id: number; amount: string; txid: string | null; status: string; created_at: string; user?: { username: string; email: string }; }

export default function AdminDeposits() {
  const [items, setItems] = useState<Deposit[]>([]);
  const [filter, setFilter] = useState("pending");
  const [error, setError] = useState("");

  const load = useCallback(() => {
    api.get(`/admin/deposits${filter ? `?status=${filter}` : ""}`).then((r) => setItems(r.data.data));
  }, [filter]);
  useEffect(() => { load(); }, [load]);

  async function act(id: number, action: "approve" | "reject") {
    setError("");
    try { await api.post(`/admin/deposits/${id}/${action}`, action === "reject" ? { note: "Rejected by admin" } : {}); load(); }
    catch (err) { setError(apiError(err)); }
  }

  return (
    <div className="space-y-5">
      <div className="flex items-center justify-between flex-wrap gap-3">
        <h1 className="text-2xl font-bold">Deposits</h1>
        <select className="input-field w-auto" value={filter} onChange={(e) => setFilter(e.target.value)}>
          <option value="">All</option><option value="pending">Pending</option><option value="approved">Approved</option><option value="rejected">Rejected</option>
        </select>
      </div>
      {error && <div className="text-sm text-red-400 bg-red-500/10 border border-red-500/30 rounded-lg px-4 py-3">{error}</div>}
      <div className="glass overflow-x-auto">
        <table className="w-full text-sm">
          <thead className="bg-white/5 text-gold-light text-left"><tr><th className="px-4 py-3">Member</th><th>Amount</th><th>TXID</th><th>Status</th><th>Date</th><th>Action</th></tr></thead>
          <tbody>
            {items.map((d) => (
              <tr key={d.id} className="border-t border-[var(--line)]">
                <td className="px-4 py-3">{d.user?.username}<div className="text-xs text-muted">{d.user?.email}</div></td>
                <td>{usdt(d.amount)}</td>
                <td className="text-xs text-muted truncate max-w-[140px]">{d.txid || "—"}</td>
                <td><StatusPill status={d.status} /></td>
                <td className="text-xs text-muted">{shortDate(d.created_at)}</td>
                <td>
                  {d.status === "pending" ? (
                    <div className="flex gap-2">
                      <button onClick={() => act(d.id, "approve")} className="btn-gold px-3 py-1 text-xs">Approve</button>
                      <button onClick={() => act(d.id, "reject")} className="btn-ghost px-3 py-1 text-xs text-red-400">Reject</button>
                    </div>
                  ) : <span className="text-xs text-muted">—</span>}
                </td>
              </tr>
            ))}
            {items.length === 0 && <tr><td colSpan={6} className="py-6 text-center text-muted">No deposits.</td></tr>}
          </tbody>
        </table>
      </div>
    </div>
  );
}
