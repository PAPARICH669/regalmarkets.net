"use client";

import { useCallback, useEffect, useState } from "react";
import api, { apiError } from "@/lib/api";
import { useAuth } from "@/lib/auth";
import { shortDate } from "@/lib/format";
import { flagEmoji } from "@/lib/countries";
import KycReviewModal from "@/components/KycReviewModal";

interface Kyc {
  id: number; username: string; name: string; email: string; phone: string;
  kyc_country: string | null; id_type: string; id_number: string; kyc_status: string; kyc_note: string | null;
  kyc_document_path: string | null; kyc_selfie_path: string | null; updated_at: string;
}

export default function StaffKycPage() {
  const user = useAuth((s) => s.user);
  const [items, setItems] = useState<Kyc[]>([]);
  const [filter, setFilter] = useState("pending");
  const [error, setError] = useState("");
  const [viewing, setViewing] = useState<Kyc | null>(null);

  const load = useCallback(() => {
    api.get(`/staff/kyc?status=${filter}`).then((r) => setItems(r.data.data)).catch((e) => setError(apiError(e)));
  }, [filter]);
  useEffect(() => { load(); }, [load]);

  if (user && !user.can_kyc) {
    return <div className="glass p-6 text-muted">You are not assigned to KYC.</div>;
  }

  async function verify(id: number) {
    try { await api.post(`/staff/members/${id}/kyc/verify`); setViewing(null); load(); } catch (e) { setError(apiError(e)); }
  }
  async function reject(id: number) {
    const note = window.prompt("Reason for rejection (optional):") ?? "";
    try { await api.post(`/staff/members/${id}/kyc/reject`, { note }); setViewing(null); load(); } catch (e) { setError(apiError(e)); }
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
            <tr><th className="px-4 py-3">Member</th><th>Country</th><th>ID Type</th><th>ID Number</th><th>Submitted</th><th>ID / Selfie</th><th>Action</th></tr>
          </thead>
          <tbody>
            {items.map((k) => (
              <tr key={k.id} className="border-t border-[var(--line)]">
                <td className="px-4 py-3">{k.name || k.username}<div className="text-xs text-muted">@{k.username} · {k.email}</div><div className="text-xs text-muted">📞 {k.phone || "—"}</div></td>
                <td className="whitespace-nowrap">{k.kyc_country ? `${flagEmoji(k.kyc_country)} ${k.kyc_country}` : "—"}</td>
                <td className="capitalize">{k.id_type}</td>
                <td className="font-mono text-xs">{k.id_number}</td>
                <td className="text-xs text-muted">{shortDate(k.updated_at)}</td>
                <td>
                  {(k.kyc_document_path || k.kyc_selfie_path)
                    ? <button onClick={() => setViewing(k)} className="btn-ghost px-3 py-1 text-xs">View</button>
                    : <span className="text-xs text-muted">—</span>}
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
            {items.length === 0 && <tr><td colSpan={7} className="py-6 text-center text-muted">No {filter} KYC submissions.</td></tr>}
          </tbody>
        </table>
      </div>

      {viewing && (
        <KycReviewModal member={viewing} basePath="/staff/members"
          onClose={() => setViewing(null)} onVerify={verify} onReject={reject} />
      )}
    </div>
  );
}
