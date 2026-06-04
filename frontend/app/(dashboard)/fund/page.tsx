"use client";

import { useEffect, useState } from "react";
import { Repeat } from "lucide-react";
import api, { apiError } from "@/lib/api";
import { useAuth } from "@/lib/auth";
import { usdt } from "@/lib/format";

export default function FundPage() {
  const { user, refresh } = useAuth();
  const [amount, setAmount] = useState("");
  const [msg, setMsg] = useState(""); const [error, setError] = useState("");
  const [loading, setLoading] = useState(false);

  useEffect(() => { refresh(); }, [refresh]);

  async function submit(e: React.FormEvent) {
    e.preventDefault(); setMsg(""); setError(""); setLoading(true);
    try { const { data } = await api.post("/fund", { amount }); setMsg(data.message); setAmount(""); refresh(); }
    catch (err) { setError(apiError(err)); } finally { setLoading(false); }
  }

  const preview = amount ? Number(amount) * 2 : 0;

  return (
    <div className="space-y-6 max-w-lg">
      <h1 className="text-2xl font-bold">Fund</h1>
      <div className="glass p-6">
        <span className="inline-grid place-items-center w-12 h-12 rounded-xl gold-gradient text-black mb-4"><Repeat size={22} /></span>
        <h3 className="font-semibold">Activate your 200% package</h3>
        <p className="text-sm text-muted mt-1">
          Fund from your <b className="text-foreground">A-WALLET</b> (capital) to start a fresh package.
          Your remaining commission becomes <b className="text-gold-light">2× (200%)</b> of the funded amount,
          paid out daily. Deposits land in A-WALLET first — fund here to activate.
        </p>
        <div className="mt-4 bg-black/30 rounded-lg p-3 text-sm flex justify-between">
          <span className="text-muted">A-WALLET balance</span>
          <span className="gold-text font-semibold">{usdt(user?.wallet_a ?? 0)}</span>
        </div>

        {msg && <div className="mt-4 text-sm text-green-400 bg-green-500/10 border border-green-500/30 rounded-lg px-4 py-3">{msg}</div>}
        {error && <div className="mt-4 text-sm text-red-400 bg-red-500/10 border border-red-500/30 rounded-lg px-4 py-3">{error}</div>}

        <form onSubmit={submit} className="mt-5 space-y-4">
          <input type="number" step="0.01" min="10" className="input-field" placeholder="Amount (USDT)" value={amount} onChange={(e) => setAmount(e.target.value)} required />
          {amount && (
            <div className="text-sm bg-black/30 rounded-lg p-3 flex justify-between">
              <span className="text-muted">Total commission (200%)</span>
              <span className="gold-text font-semibold">{usdt(preview)}</span>
            </div>
          )}
          <button disabled={loading} className="btn-gold w-full py-2.5">{loading ? "Processing…" : "Fund Now"}</button>
        </form>
      </div>
    </div>
  );
}
