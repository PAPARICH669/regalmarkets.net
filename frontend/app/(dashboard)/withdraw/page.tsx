"use client";

import { useEffect, useState } from "react";
import { Coins } from "lucide-react";
import api, { apiError } from "@/lib/api";
import { useAuth } from "@/lib/auth";
import { usdt, shortDate } from "@/lib/format";
import StatusPill from "@/components/StatusPill";
import KycBanner from "@/components/KycBanner";

interface Withdrawal { id: number; amount: string; fee: string; net_amount: string; wallet_address: string; txid: string | null; status: string; created_at: string; }
interface Cfg { min: number; max_amount: number; fee_flat: number; max_per_day: number; processing_hours: number; window_start: string; window_end: string; }

export default function WithdrawPage() {
  const { user, refresh } = useAuth();
  const [amount, setAmount] = useState("");
  const [address, setAddress] = useState("");
  const [cfg, setCfg] = useState<Cfg | null>(null);
  const [msg, setMsg] = useState(""); const [error, setError] = useState("");
  const [loading, setLoading] = useState(false);
  const [history, setHistory] = useState<Withdrawal[]>([]);

  const load = () => api.get("/withdrawals").then((r) => setHistory(r.data.data));
  useEffect(() => { load(); api.get("/withdrawals/config").then((r) => setCfg(r.data)); }, []);
  useEffect(() => { if (user?.wallet_address) setAddress(user.wallet_address); }, [user?.wallet_address]);

  const fee = cfg ? cfg.fee_flat : 0;

  async function submit(e: React.FormEvent) {
    e.preventDefault();
    setMsg(""); setError(""); setLoading(true);
    try {
      const { data } = await api.post("/withdrawals", { amount, wallet_address: address });
      setMsg(data.message); setAmount("");
      load(); refresh();
    } catch (err) { setError(apiError(err)); } finally { setLoading(false); }
  }

  return (
    <div className="space-y-6">
      <h1 className="text-2xl font-bold">Withdraw USDT</h1>
      <KycBanner action="withdraw" />
      <div className="grid lg:grid-cols-2 gap-6">
        <div className="glass p-6">
          <h3 className="font-semibold">Request Withdrawal</h3>
          <p className="text-sm text-muted mt-1">
            From E-WALLET. Min {cfg ? usdt(cfg.min) : "…"}, max {cfg ? usdt(cfg.max_amount) : "…"} per withdrawal.
            <b className="text-foreground"> {cfg?.max_per_day ?? 1} withdrawal/day.</b> Flat fee {cfg ? usdt(cfg.fee_flat) : "…"}.
            Processed within {cfg?.processing_hours ?? 72} working hours (Mon–Fri, weekends excluded).
          </p>
          {cfg && (
            <div className="mt-3 text-sm text-gold-light bg-gold-light/5 border border-gold-light/20 rounded-lg px-4 py-2">
              ⏰ Withdrawal requests are accepted only between <b>{cfg.window_start}–{cfg.window_end}</b> (Malaysia time).
            </div>
          )}
          <div className="mt-4 flex items-center justify-between bg-black/30 rounded-lg p-3">
            <span className="text-sm text-muted flex items-center gap-2"><Coins size={16} className="text-gold-light" /> E-WALLET balance</span>
            <span className="text-lg font-bold gold-text">{usdt(user?.wallet_e ?? 0)}</span>
          </div>
          {msg && <div className="mt-4 text-sm text-green-400 bg-green-500/10 border border-green-500/30 rounded-lg px-4 py-3">{msg}</div>}
          {error && <div className="mt-4 text-sm text-red-400 bg-red-500/10 border border-red-500/30 rounded-lg px-4 py-3">{error}</div>}
          <form onSubmit={submit} className="mt-5 space-y-4">
            <div>
              <label className="text-sm text-muted">Amount (USDT)</label>
              <input type="number" step="0.01" className="input-field mt-1" value={amount} onChange={(e) => setAmount(e.target.value)} required />
            </div>
            <div>
              <label className="text-sm text-muted">
                USDT wallet address <span className="text-gold-light font-medium">(BEP20 only)</span>
              </label>
              <input className="input-field mt-1" value={address} onChange={(e) => setAddress(e.target.value)}
                placeholder="0x… (BEP20 / BSC network)" required />
              <p className="text-xs text-muted mt-1">⚠️ Use a BEP20 (BSC) USDT address only. Wrong-network withdrawals are unrecoverable.</p>
            </div>
            {amount && cfg && (
              <div className="text-sm bg-black/30 rounded-lg p-3 space-y-1">
                <div className="flex justify-between"><span className="text-muted">Flat fee</span><span>{usdt(fee)}</span></div>
                <div className="flex justify-between font-semibold"><span>You receive</span><span className="gold-text">{usdt(Math.max(Number(amount) - fee, 0))}</span></div>
              </div>
            )}
            <button disabled={loading} className="btn-gold w-full py-2.5">{loading ? "Requesting…" : "Request Withdrawal"}</button>
          </form>
        </div>

        <div className="glass p-6">
          <h3 className="font-semibold mb-4">Withdrawal History</h3>
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead className="text-gold-light text-left"><tr><th className="py-2">Amount</th><th>Net</th><th>Status</th><th>Date</th></tr></thead>
              <tbody>
                {history.map((w) => (
                  <tr key={w.id} className="border-t border-[var(--line)]">
                    <td className="py-2">{usdt(w.amount)}</td>
                    <td>{usdt(w.net_amount)}</td>
                    <td><StatusPill status={w.status} /></td>
                    <td className="text-muted text-xs">{shortDate(w.created_at)}</td>
                  </tr>
                ))}
                {history.length === 0 && <tr><td colSpan={4} className="py-4 text-muted text-center">No withdrawals yet.</td></tr>}
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  );
}
