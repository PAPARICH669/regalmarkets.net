"use client";

import { useCallback, useEffect, useState } from "react";
import { X, AlertTriangle } from "lucide-react";
import api, { apiError } from "@/lib/api";
import { usdt, shortDate } from "@/lib/format";
import StatusPill from "@/components/StatusPill";

interface Withdrawal {
  id: number; amount: string; fee: string; net_amount: string; wallet_address: string;
  status: string; created_at: string; user?: { username: string; email: string };
  coin?: string; network?: string; coin_address?: string; system_rate?: string;
  coin_fee?: string; coin_amount_est?: string; coin_amount_actual?: string | null;
}

function trimCoin(v: string | number | null | undefined, dp = 8) {
  const n = Number(v ?? 0);
  return n.toLocaleString("en-US", { minimumFractionDigits: 0, maximumFractionDigits: dp });
}
const isSwap = (w: Withdrawal) => !!w.coin && w.coin !== "USDT";

const REJECT_REASONS = [
  "Payout address is incorrect or invalid",
  "KYC not completed / verification required",
  "Suspicious or fraudulent activity",
  "Duplicate or mistaken request",
  "Does not meet withdrawal terms",
];

export default function AdminWithdrawals() {
  const [items, setItems] = useState<Withdrawal[]>([]);
  const [filter, setFilter] = useState("pending");
  const [error, setError] = useState("");
  const [confirming, setConfirming] = useState<Withdrawal | null>(null);
  const [txid, setTxid] = useState("");
  const [coinActual, setCoinActual] = useState("");
  const [rejecting, setRejecting] = useState<Withdrawal | null>(null);
  const [rejectReason, setRejectReason] = useState("");
  const [busy, setBusy] = useState(false);

  const load = useCallback(() => {
    api.get(`/admin/withdrawals${filter ? `?status=${filter}` : ""}`).then((r) => setItems(r.data.data)).catch((e) => setError(apiError(e)));
  }, [filter]);
  useEffect(() => { load(); }, [load]);

  function openApprove(w: Withdrawal) {
    setConfirming(w); setTxid(""); setError("");
    setCoinActual(isSwap(w) ? trimCoin(w.coin_amount_est) : "");
  }

  async function confirmApprove() {
    if (!confirming) return;
    setError(""); setBusy(true);
    try {
      const payload: Record<string, string> = { txid: txid.trim() };
      if (isSwap(confirming) && coinActual.trim()) payload.coin_amount_actual = coinActual.trim();
      await api.post(`/admin/withdrawals/${confirming.id}/approve`, payload);
      setConfirming(null); setTxid(""); setCoinActual(""); load();
    } catch (err) { setError(apiError(err)); } finally { setBusy(false); }
  }
  function openReject(w: Withdrawal) {
    setRejecting(w); setRejectReason(""); setError("");
  }
  async function confirmReject() {
    if (!rejecting) return;
    const reason = rejectReason.trim();
    if (!reason) { setError("Please enter a reason for rejecting this withdrawal."); return; }
    setError(""); setBusy(true);
    try {
      await api.post(`/admin/withdrawals/${rejecting.id}/reject`, { note: reason });
      setRejecting(null); setRejectReason(""); load();
    } catch (err) { setError(apiError(err)); } finally { setBusy(false); }
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
          <thead className="bg-white/5 text-gold-light text-left"><tr><th className="px-4 py-3">Member</th><th>Amount</th><th>Receive</th><th>Address</th><th>Status</th><th>Action</th></tr></thead>
          <tbody>
            {items.map((w) => (
              <tr key={w.id} className="border-t border-[var(--line)]">
                <td className="px-4 py-3">{w.user?.username}<div className="text-xs text-muted">{shortDate(w.created_at)}</div></td>
                <td>{usdt(w.amount)}</td>
                <td>
                  {isSwap(w) ? (
                    <div>
                      <span className="text-[10px] px-1.5 py-0.5 rounded bg-gold-light/15 text-gold-light mr-1">{w.coin}</span>
                      {trimCoin(w.coin_amount_actual ?? w.coin_amount_est)} {w.coin}
                      {!w.coin_amount_actual && <span className="text-xs text-muted"> (est)</span>}
                    </div>
                  ) : usdt(w.net_amount)}
                </td>
                <td className="text-xs text-muted truncate max-w-[140px]">{w.coin_address || w.wallet_address}</td>
                <td><StatusPill status={w.status} /></td>
                <td>
                  {w.status === "pending" ? (
                    <div className="flex gap-2">
                      <button onClick={() => openApprove(w)} className="btn-gold px-3 py-1 text-xs">Approve</button>
                      <button onClick={() => openReject(w)} className="btn-ghost px-3 py-1 text-xs text-red-400">Reject</button>
                    </div>
                  ) : <span className="text-xs text-muted">—</span>}
                </td>
              </tr>
            ))}
            {items.length === 0 && <tr><td colSpan={6} className="py-6 text-center text-muted">No withdrawals.</td></tr>}
          </tbody>
        </table>
      </div>

      {/* Approve confirmation */}
      {confirming && (
        <div className="fixed inset-0 z-50 bg-black/70 flex items-center justify-center p-4" onClick={() => !busy && setConfirming(null)}>
          <div className="w-full max-w-md rounded-2xl border border-[var(--line)] bg-[var(--surface)]" onClick={(e) => e.stopPropagation()}>
            <div className="flex items-center gap-2 px-4 py-3 border-b border-[var(--line)]">
              <span className="font-semibold flex-1">Confirm Withdrawal</span>
              <button onClick={() => setConfirming(null)} aria-label="Close" className="btn-ghost p-2"><X size={16} /></button>
            </div>
            <div className="p-4 space-y-3">
              <div className="rounded-lg bg-amber-500/10 border border-amber-500/30 px-3 py-2 text-xs text-amber-300 flex items-start gap-2">
                <AlertTriangle size={14} className="mt-0.5 shrink-0" /> Review the details carefully before confirming. Once approved, the payment is considered made.
              </div>
              <div className="space-y-1.5 text-sm bg-black/30 rounded-lg p-3">
                <Row k="Member" v={`@${confirming.user?.username}`} />
                <Row k="Amount (gross)" v={`${usdt(confirming.amount)} USDT`} />
                {isSwap(confirming) ? (
                  <>
                    <Row k="Receive in" v={`${confirming.coin} (${confirming.network})`} />
                    <Row k="System rate" v={`${trimCoin(confirming.system_rate, 2)} USDT`} />
                    <Row k="Network fee" v={`${trimCoin(confirming.coin_fee)} ${confirming.coin}`} />
                    <Row k="Estimated (net)" v={`${trimCoin(confirming.coin_amount_est)} ${confirming.coin}`} bold />
                  </>
                ) : (
                  <>
                    <Row k="Fee" v={`${usdt(confirming.fee)} USDT`} />
                    <Row k="Net (paid)" v={`${usdt(confirming.net_amount)} USDT`} bold />
                  </>
                )}
              </div>
              <div>
                <span className="text-xs text-muted">Payout address ({confirming.network || "BEP20"})</span>
                <div className="font-mono text-xs break-all bg-black/30 rounded-lg p-2 mt-1">{confirming.coin_address || confirming.wallet_address}</div>
              </div>
              {isSwap(confirming) && (
                <div>
                  <label className="text-xs text-muted">ACTUAL {confirming.coin} amount sent <span className="text-gold-light">(final)</span></label>
                  <input className="input-field mt-1 font-mono text-sm" value={coinActual} onChange={(e) => setCoinActual(e.target.value)} placeholder={`e.g. ${trimCoin(confirming.coin_amount_est)}`} inputMode="decimal" />
                  <p className="text-[11px] text-muted mt-1">Enter the actual coin amount you sent. This becomes the final record.</p>
                </div>
              )}
              <div>
                <label className="text-xs text-muted">Payout TXID (optional — payment transaction hash)</label>
                <input className="input-field mt-1 font-mono text-sm" value={txid} onChange={(e) => setTxid(e.target.value)} placeholder="0x… (if any)" />
              </div>
              <div className="flex gap-2 pt-1">
                <button onClick={() => setConfirming(null)} disabled={busy} className="btn-ghost flex-1 py-2.5 text-sm">Cancel</button>
                <button onClick={confirmApprove} disabled={busy} className="btn-gold flex-[2] py-2.5 text-sm">{busy ? "Processing…" : "✓ Confirm & Approve"}</button>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* Reject with reason */}
      {rejecting && (
        <div className="fixed inset-0 z-50 bg-black/70 flex items-center justify-center p-4" onClick={() => !busy && setRejecting(null)}>
          <div className="w-full max-w-md rounded-2xl border border-[var(--line)] bg-[var(--surface)]" onClick={(e) => e.stopPropagation()}>
            <div className="flex items-center gap-2 px-4 py-3 border-b border-[var(--line)]">
              <span className="font-semibold flex-1">Reject Withdrawal</span>
              <button onClick={() => setRejecting(null)} aria-label="Close" className="btn-ghost p-2"><X size={16} /></button>
            </div>
            <div className="p-4 space-y-3">
              <div className="rounded-lg bg-amber-500/10 border border-amber-500/30 px-3 py-2 text-xs text-amber-300 flex items-start gap-2">
                <AlertTriangle size={14} className="mt-0.5 shrink-0" /> The held {usdt(rejecting.amount)} USDT is refunded to @{rejecting.user?.username}&apos;s E-WALLET, and the member is emailed this reason.
              </div>
              <div>
                <span className="text-xs text-muted">Quick reasons</span>
                <div className="flex flex-wrap gap-1.5 mt-1.5">
                  {REJECT_REASONS.map((r) => (
                    <button key={r} type="button" onClick={() => setRejectReason(r)}
                      className={`text-[11px] px-2 py-1 rounded-full border transition-colors ${rejectReason === r ? "border-gold-light text-gold-light bg-gold-light/10" : "border-[var(--line)] text-muted hover:text-white"}`}>
                      {r}
                    </button>
                  ))}
                </div>
              </div>
              <div>
                <label className="text-xs text-muted">Reason (shown to the member)</label>
                <textarea className="input-field mt-1 w-full" rows={3} maxLength={255} value={rejectReason}
                  onChange={(e) => setRejectReason(e.target.value)} placeholder="Enter the reason for rejecting this withdrawal…" />
                <p className="text-[11px] text-muted mt-1 text-right">{rejectReason.length}/255</p>
              </div>
              <div className="flex gap-2 pt-1">
                <button onClick={() => setRejecting(null)} disabled={busy} className="btn-ghost flex-1 py-2.5 text-sm">Cancel</button>
                <button onClick={confirmReject} disabled={busy || !rejectReason.trim()}
                  className="flex-[2] py-2.5 text-sm rounded-lg bg-red-500/90 hover:bg-red-500 text-white font-semibold disabled:opacity-50">
                  {busy ? "Processing…" : "Reject & Refund"}
                </button>
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}

function Row({ k, v, bold }: { k: string; v: string; bold?: boolean }) {
  return <div className="flex justify-between"><span className="text-muted">{k}</span><span className={bold ? "font-bold gold-text" : "font-medium"}>{v}</span></div>;
}
