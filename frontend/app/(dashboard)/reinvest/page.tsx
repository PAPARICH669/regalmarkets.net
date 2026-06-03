"use client";

import { useEffect, useState } from "react";
import { Repeat } from "lucide-react";
import api, { apiError } from "@/lib/api";
import { useAuth } from "@/lib/auth";
import { usdt } from "@/lib/format";

export default function ReinvestPage() {
  const { user, refresh } = useAuth();
  const [amount, setAmount] = useState("");
  const [msg, setMsg] = useState(""); const [error, setError] = useState("");
  const [loading, setLoading] = useState(false);

  useEffect(() => { refresh(); }, [refresh]);

  async function submit(e: React.FormEvent) {
    e.preventDefault(); setMsg(""); setError(""); setLoading(true);
    try { const { data } = await api.post("/reinvest", { amount }); setMsg(data.message); setAmount(""); refresh(); }
    catch (err) { setError(apiError(err)); } finally { setLoading(false); }
  }

  return (
    <div className="space-y-6 max-w-lg">
      <h1 className="text-2xl font-bold">Reinvest</h1>
      <div className="glass p-6">
        <span className="inline-grid place-items-center w-12 h-12 rounded-xl gold-gradient text-black mb-4"><Repeat size={22} /></span>
        <h3 className="font-semibold">Compound your earnings</h3>
        <p className="text-sm text-muted mt-1">
          Reinvest from your E-WALLET (min 10 USDT) to activate a fresh 200% package.
          Funds route through your A-WALLET and lock into the new package.
        </p>
        <div className="mt-4 bg-black/30 rounded-lg p-3 text-sm flex justify-between">
          <span className="text-muted">E-WALLET balance</span>
          <span className="gold-text font-semibold">{usdt(user?.wallet_e ?? 0)}</span>
        </div>

        {msg && <div className="mt-4 text-sm text-green-400 bg-green-500/10 border border-green-500/30 rounded-lg px-4 py-3">{msg}</div>}
        {error && <div className="mt-4 text-sm text-red-400 bg-red-500/10 border border-red-500/30 rounded-lg px-4 py-3">{error}</div>}

        <form onSubmit={submit} className="mt-5 space-y-4">
          <input type="number" step="0.01" min="10" className="input-field" placeholder="Amount (USDT)" value={amount} onChange={(e) => setAmount(e.target.value)} required />
          <button disabled={loading} className="btn-gold w-full py-2.5">{loading ? "Processing…" : "Reinvest Now"}</button>
        </form>
      </div>
    </div>
  );
}
