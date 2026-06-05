"use client";

import { useCallback, useEffect, useState } from "react";
import api, { apiError } from "@/lib/api";
import { shortDate } from "@/lib/format";

interface Kyc {
  id: number; username: string; name: string; email: string; phone: string;
  id_type: string; id_number: string; kyc_status: string; kyc_note: string | null;
  kyc_document_path: string | null; updated_at: string;
}

export default function AdminKycPage() {
  const [items, setItems] = useState<Kyc[]>([]);
  const [filter, setFilter] = useState("pending");
  const [error, setError] = useState("");

  const load = useCallback(() => {
    api.get(`/admin/kyc?status=${filter}`).then((r) => setItems(r.data.data)).catch((e) => setError(apiError(e)));
  }, [filter]);
  useEffect(() => { load(); }, [load]);

  async function viewDoc(id: number) {
    try {
      const res = await api.get(`/admin/members/${id}/kyc-document`, { responseType: "blob" });
      const url = window.URL.createObjectURL(res.data);
      window.open(url, "_blank");
    } catch (e) { setError(apiError(e)); }
  }
  async function verify(id: number) {
    try { await api.post(`/admin/members/${id}/kyc/verify`); load(); } catch (e) { setError(apiError(e)); }
  }
  async function reject(id: number) {
    const note = window.prompt("Reason for rejection (optional):") ?? "";
    try { await api.post(`/admin/members/${id}/kyc/reject`, { note }); load(); } catch (e) { setError(apiError(e)); }
  }

  return (
    <div className="space-y-5">
      <div className="flex items-center justify-between flex-wrap gap-3">
        <h1 className="text-2xl font-bold">KYC Verification</h1>
        <select className="input-field w-auto" value={filter} onChange={(e) => setFilter(e.target.value)}>
          <option value="pending">Pending</option>
          <option value="unsubmitted">Unsubmitted</option>
          <option value="verified">Verified</option>
          <option value="rejected">Rejected</option>
        </select>
      </div>
      {error && <div className="text-sm text-red-400 bg-red-500/10 border border-red-500/30 rounded-lg px-4 py-3">{error}</div>}
      <div className="glass overflow-x-auto">
        <table className="w-full text-sm">
          <thead className="bg-white/5 text-gold-light text-left">
            <tr><th className="px-4 py-3">Member</th><th>ID Type</th><th>ID Number</th><th>Submitted</th><th>Document</th><th>Action</th></tr>
          </thead>
          <tbody>
            {items.map((k) => (
              <tr key={k.id} className="border-t border-[var(--line)]">
                <td className="px-4 py-3">{k.name || k.username}<div className="text-xs text-muted">@{k.username} · {k.email}</div><div className="text-xs text-muted">📞 {k.phone || "—"}</div></td>
                <td className="capitalize">{k.id_type}</td>
                <td className="font-mono text-xs">{k.id_number}</td>
                <td className="text-xs text-muted">{shortDate(k.updated_at)}</td>
                <td>
                  {k.kyc_document_path
                    ? <button onClick={() => viewDoc(k.id)} className="btn-ghost px-3 py-1 text-xs">View ID</button>
                    : <span className="text-xs text-muted">No document</span>}
                </td>
                <td>
                  {k.kyc_status !== "verified" ? (
                    <div className="flex gap-2">
                      <button onClick={() => verify(k.id)} className="btn-gold px-3 py-1 text-xs">Verify</button>
                      <button onClick={() => reject(k.id)} className="btn-ghost px-3 py-1 text-xs text-red-400">Reject</button>
                    </div>
                  ) : <span className="text-xs text-green-400">Verified</span>}
                </td>
              </tr>
            ))}
            {items.length === 0 && <tr><td colSpan={6} className="py-6 text-center text-muted">No {filter} KYC submissions.</td></tr>}
          </tbody>
        </table>
      </div>
    </div>
  );
}
