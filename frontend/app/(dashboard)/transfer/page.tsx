"use client";

import { useEffect, useState } from "react";
import api, { apiError } from "@/lib/api";
import { usdt, shortDate } from "@/lib/format";
import { useAuth } from "@/lib/auth";

interface Transfer { id: number; from_user?: { username: string }; to_user?: { username: string } | null; from_wallet: string; to_wallet: string; amount: string; type: string; created_at: string; }

export default function TransferPage() {
  const refresh = useAuth((s) => s.refresh);
  const [tab, setTab] = useState<"self" | "member">("self");
  const [selfAmount, setSelfAmount] = useState("");
  const [to, setTo] = useState(""); const [memberAmount, setMemberAmount] = useState("");
  const [msg, setMsg] = useState(""); const [error, setError] = useState("");
  const [loading, setLoading] = useState(false);
  const [history, setHistory] = useState<Transfer[]>([]);

  const load = () => api.get("/transfers").then((r) => setHistory(r.data.data));
  useEffect(() => { load(); }, []);

  async function selfTransfer(e: React.FormEvent) {
    e.preventDefault(); setMsg(""); setError(""); setLoading(true);
    try { const { data } = await api.post("/transfers/self", { amount: selfAmount }); setMsg(data.message); setSelfAmount(""); load(); refresh(); }
    catch (err) { setError(apiError(err)); } finally { setLoading(false); }
  }
  async function memberTransfer(e: React.FormEvent) {
    e.preventDefault(); setMsg(""); setError(""); setLoading(true);
    try { const { data } = await api.post("/transfers/member", { to, amount: memberAmount }); setMsg(data.message); setTo(""); setMemberAmount(""); load(); refresh(); }
    catch (err) { setError(apiError(err)); } finally { setLoading(false); }
  }

  return (
    <div className="space-y-6">
      <h1 className="text-2xl font-bold">Transfer</h1>
      <div className="grid lg:grid-cols-2 gap-6">
        <div className="glass p-6">
          <div className="flex gap-2 mb-5">
            <button onClick={() => setTab("self")} className={`px-4 py-2 rounded-lg text-sm ${tab === "self" ? "gold-gradient text-black font-semibold" : "btn-ghost"}`}>E → A (self)</button>
            <button onClick={() => setTab("member")} className={`px-4 py-2 rounded-lg text-sm ${tab === "member" ? "gold-gradient text-black font-semibold" : "btn-ghost"}`}>Member to Member</button>
          </div>

          {msg && <div className="mb-4 text-sm text-green-400 bg-green-500/10 border border-green-500/30 rounded-lg px-4 py-3">{msg}</div>}
          {error && <div className="mb-4 text-sm text-red-400 bg-red-500/10 border border-red-500/30 rounded-lg px-4 py-3">{error}</div>}

          {tab === "self" ? (
            <form onSubmit={selfTransfer} className="space-y-4">
              <p className="text-sm text-muted">Move earnings from E-WALLET to A-WALLET (min 10 USDT). A → E is not allowed.</p>
              <input type="number" step="0.01" className="input-field" placeholder="Amount (USDT)" value={selfAmount} onChange={(e) => setSelfAmount(e.target.value)} required />
              <button disabled={loading} className="btn-gold w-full py-2.5">Transfer to A-WALLET</button>
            </form>
          ) : (
            <form onSubmit={memberTransfer} className="space-y-4">
              <p className="text-sm text-muted">Send A-WALLET funds to another member (min 10 USDT).</p>
              <input className="input-field" placeholder="Recipient username or email" value={to} onChange={(e) => setTo(e.target.value)} required />
              <input type="number" step="0.01" className="input-field" placeholder="Amount (USDT)" value={memberAmount} onChange={(e) => setMemberAmount(e.target.value)} required />
              <button disabled={loading} className="btn-gold w-full py-2.5">Send Transfer</button>
            </form>
          )}
        </div>

        <div className="glass p-6">
          <h3 className="font-semibold mb-4">Transfer History</h3>
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead className="text-gold-light text-left"><tr><th className="py-2">Type</th><th>Route</th><th>Amount</th><th>Date</th></tr></thead>
              <tbody>
                {history.map((t) => (
                  <tr key={t.id} className="border-t border-[var(--line)]">
                    <td className="py-2">{t.type === "internal_self" ? "Self" : "Member"}</td>
                    <td className="text-muted text-xs">{t.type === "internal_self" ? `${t.from_wallet}→${t.to_wallet}` : `${t.from_user?.username ?? ""} → ${t.to_user?.username ?? ""}`}</td>
                    <td>{usdt(t.amount)}</td>
                    <td className="text-muted text-xs">{shortDate(t.created_at)}</td>
                  </tr>
                ))}
                {history.length === 0 && <tr><td colSpan={4} className="py-4 text-muted text-center">No transfers yet.</td></tr>}
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  );
}
