"use client";

import { useEffect, useState } from "react";
import { ArrowLeftRight, Wallet, Mail } from "lucide-react";
import api, { apiError } from "@/lib/api";
import { useAuth } from "@/lib/auth";
import { usdt, shortDate } from "@/lib/format";

interface Hist { id: number; direction: string; amount: string; note: string | null; to_username: string | null; created_at: string; }

export default function TransferPage() {
  const { user, refresh } = useAuth();
  const [step, setStep] = useState<"idle" | "tac">("idle");
  const [memberId, setMemberId] = useState("");
  const [amount, setAmount] = useState("");
  const [recipient, setRecipient] = useState<{ id: number; username: string; name: string } | null>(null);
  const [code, setCode] = useState("");
  const [msg, setMsg] = useState(""); const [err, setErr] = useState(""); const [loading, setLoading] = useState(false);
  const [history, setHistory] = useState<Hist[]>([]);

  const loadHistory = () => api.get("/ld/wallet-history").then((r) => setHistory(r.data)).catch(() => {});
  useEffect(() => { refresh(); loadHistory(); }, [refresh]);

  if (user && !user.is_ld) {
    return <div className="glass p-6 text-muted">This page is for LD accounts only.</div>;
  }

  async function startTransfer(e: React.FormEvent) {
    e.preventDefault(); setErr(""); setMsg(""); setLoading(true);
    try {
      const amt = parseFloat(amount);
      if (!amt || amt < 1) { setErr("Minimum transfer is 1 USDT."); setLoading(false); return; }
      const { data } = await api.post("/ld/transfer/init", { member_id: Number(memberId), amount: amt });
      setRecipient(data.recipient); setCode(""); setStep("tac"); setMsg(data.message);
    } catch (e) { setErr(apiError(e, "Member ID not found.")); } finally { setLoading(false); }
  }

  async function confirmTransfer() {
    setErr(""); setMsg(""); setLoading(true);
    try {
      const { data } = await api.post("/ld/transfer/confirm", { code });
      setMsg(data.message); setStep("idle"); setMemberId(""); setAmount(""); setCode(""); setRecipient(null);
      refresh(); loadHistory();
    } catch (e) { setErr(apiError(e)); } finally { setLoading(false); }
  }

  return (
    <div className="space-y-6">
      <h1 className="text-2xl font-bold">Transfer</h1>

      <div className="grid lg:grid-cols-2 gap-6">
        <div className="glass p-6">
          <div className="flex items-center justify-between bg-gold-light/10 border border-gold-light/30 rounded-lg p-3">
            <span className="text-sm text-gold-light flex items-center gap-2"><Wallet size={16} /> LD WALLET</span>
            <span className="text-lg font-bold gold-text">{usdt(user?.wallet_l ?? 0)}</span>
          </div>
          <div className="flex items-center gap-2 mt-4"><ArrowLeftRight size={18} className="text-gold-light" /><h3 className="font-semibold">Transfer kredit</h3></div>
          <p className="text-sm text-muted mt-1">Dari LD WALLET ke A-WALLET ahli. Minimum 1 USDT. Setiap transfer perlu kod TAC.</p>

          {msg && <div className="mt-4 text-sm text-green-400 bg-green-500/10 border border-green-500/30 rounded-lg px-4 py-3">{msg}</div>}
          {err && <div className="mt-4 text-sm text-red-400 bg-red-500/10 border border-red-500/30 rounded-lg px-4 py-3">{err}</div>}

          {step === "idle" ? (
            <form onSubmit={startTransfer} className="mt-5 space-y-4">
              <div>
                <label className="text-sm text-muted">ID ahli</label>
                <input type="number" className="input-field mt-1" value={memberId} onChange={(e) => setMemberId(e.target.value)} placeholder="cth: 24" required />
              </div>
              <div>
                <label className="text-sm text-muted">Jumlah (USDT)</label>
                <input type="number" step="0.01" min="1" className="input-field mt-1" value={amount} onChange={(e) => setAmount(e.target.value)} placeholder="cth: 100" required />
              </div>
              <button disabled={loading} className="btn-gold w-full py-2.5">{loading ? "Menyemak…" : "Hantar"}</button>
            </form>
          ) : (
            <div className="mt-5 border border-[var(--line)] rounded-lg p-4 bg-black/20">
              <div className="flex items-center gap-3 mb-3">
                <div className="w-10 h-10 rounded-full bg-gold-light/15 grid place-items-center text-gold-light font-semibold">
                  {(recipient?.name || recipient?.username || "?").slice(0, 2).toUpperCase()}
                </div>
                <div>
                  <p className="font-semibold">{recipient?.name}</p>
                  <p className="text-xs text-muted">@{recipient?.username} · ID {recipient?.id}</p>
                </div>
              </div>
              <div className="flex justify-between text-sm border-t border-[var(--line)] pt-2 mb-3">
                <span className="text-muted">Masuk ke A-WALLET</span><span className="font-semibold">{usdt(amount)}</span>
              </div>
              <p className="text-xs text-muted mb-2 flex items-center gap-1"><Mail size={13} /> Kod TAC dihantar ke email anda.</p>
              <input inputMode="numeric" maxLength={6} className="input-field text-center tracking-[0.4em] font-mono"
                placeholder="······" value={code} onChange={(e) => setCode(e.target.value.replace(/\D/g, ""))} />
              <div className="flex gap-2 mt-3">
                <button onClick={() => { setStep("idle"); setRecipient(null); setMsg(""); }} className="btn-ghost flex-1 py-2 text-sm">Batal</button>
                <button onClick={confirmTransfer} disabled={loading || code.length < 6} className="btn-gold flex-[2] py-2 text-sm">{loading ? "Menghantar…" : "Sahkan & hantar"}</button>
              </div>
            </div>
          )}
        </div>

        <div className="glass p-6">
          <h3 className="font-semibold mb-4">History LD WALLET</h3>
          <div className="space-y-2">
            {history.map((h) => {
              const out = h.direction === "debit";
              return (
                <div key={h.id} className="flex items-center justify-between bg-black/30 rounded-lg px-3 py-2.5 text-sm">
                  <div className="min-w-0">
                    <p className="font-medium truncate">{out ? <>Transfer ke <span className="text-gold-light">@{h.to_username || "—"}</span></> : "Top-up (admin)"}</p>
                    <p className="text-[11px] text-muted">{shortDate(h.created_at)}</p>
                  </div>
                  <span className={`font-semibold shrink-0 ${out ? "text-red-400" : "text-green-400"}`}>{out ? "−" : "+"}{usdt(h.amount)}</span>
                </div>
              );
            })}
            {history.length === 0 && <p className="text-sm text-muted text-center py-4">Belum ada transaksi LD WALLET.</p>}
          </div>
        </div>
      </div>
    </div>
  );
}
