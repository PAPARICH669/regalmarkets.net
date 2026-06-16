"use client";

import { useEffect, useState } from "react";
import api, { apiError } from "@/lib/api";
import { shortDate } from "@/lib/format";

interface Req {
  id: number; field: string; old_value: string | null; new_value: string; status: string;
  created_at: string; user?: { id: number; username: string; email: string };
}

export default function AdminChangeRequests() {
  const [items, setItems] = useState<Req[]>([]);
  const [error, setError] = useState(""); const [msg, setMsg] = useState("");

  const load = () => api.get("/admin/change-requests").then((r) => setItems(r.data)).catch((e) => setError(apiError(e)));
  useEffect(() => { load(); }, []);

  async function approve(id: number) {
    setMsg(""); setError("");
    if (!window.confirm("Approve and apply this change to the member?")) return;
    try { const { data } = await api.post(`/admin/change-requests/${id}/approve`); setMsg(data.message); load(); }
    catch (e) { setError(apiError(e)); }
  }
  async function reject(id: number) {
    const note = window.prompt("Reason for rejection (optional):");
    if (note === null) return;
    setMsg(""); setError("");
    try { const { data } = await api.post(`/admin/change-requests/${id}/reject`, { note }); setMsg(data.message); load(); }
    catch (e) { setError(apiError(e)); }
  }

  return (
    <div className="space-y-5">
      <h1 className="text-2xl font-bold">Change Requests</h1>
      <p className="text-sm text-muted">Member-initiated <b>email</b> / <b>wallet address</b> changes awaiting approval. Members have already confirmed via an email code (TAC).</p>
      {msg && <div className="text-sm text-green-400 bg-green-500/10 border border-green-500/30 rounded-lg px-4 py-3">{msg}</div>}
      {error && <div className="text-sm text-red-400 bg-red-500/10 border border-red-500/30 rounded-lg px-4 py-3">{error}</div>}

      <div className="glass overflow-x-auto">
        <table className="w-full text-sm">
          <thead className="bg-white/5 text-gold-light text-left">
            <tr><th className="px-4 py-3">Member</th><th>Field</th><th>Current</th><th>New value</th><th>Requested</th><th>Actions</th></tr>
          </thead>
          <tbody>
            {items.map((r) => (
              <tr key={r.id} className="border-t border-[var(--line)]">
                <td className="px-4 py-3">{r.user?.username || "—"}<div className="text-xs text-muted break-all">{r.user?.email}</div></td>
                <td>{r.field === "email" ? "Email" : "Wallet"}</td>
                <td className="text-muted break-all max-w-[180px]">{r.old_value || "—"}</td>
                <td className="text-gold-light break-all max-w-[200px]">{r.new_value}</td>
                <td className="text-xs text-muted whitespace-nowrap">{shortDate(r.created_at)}</td>
                <td>
                  <div className="flex gap-1.5">
                    <button onClick={() => approve(r.id)} className="px-2.5 py-1 text-xs rounded bg-green-500/15 text-green-400 hover:bg-green-500/25">Approve</button>
                    <button onClick={() => reject(r.id)} className="px-2.5 py-1 text-xs rounded bg-red-500/15 text-red-400 hover:bg-red-500/25">Reject</button>
                  </div>
                </td>
              </tr>
            ))}
            {items.length === 0 && <tr><td colSpan={6} className="py-6 text-center text-muted">No pending change requests.</td></tr>}
          </tbody>
        </table>
      </div>
    </div>
  );
}
