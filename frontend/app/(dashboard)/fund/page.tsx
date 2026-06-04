"use client";

import { useEffect, useState } from "react";
import { Repeat } from "lucide-react";
import api, { apiError } from "@/lib/api";
import { useAuth } from "@/lib/auth";
import { usdt, shortDate } from "@/lib/format";
import StatusPill from "@/components/StatusPill";

interface Package {
  id: number; principal: string; total_return: string; total_paid: string;
  status: string; source: string; created_at: string;
}

export default function FundPage() {
  const { user, refresh } = useAuth();
  const [amount, setAmount] = useState("");
  const [msg, setMsg] = useState(""); const [error, setError] = useState("");
  const [loading, setLoading] = useState(false);
  const [history, setHistory] = useState<Package[]>([]);

  const load = () => api.get("/fund").then((r) => setHistory(r.data.data));
  useEffect(() => { refresh(); load(); }, [refresh]);

  async function submit(e: React.FormEvent) {
    e.preventDefault(); setMsg(""); setError(""); setLoading(true);
    try { const { data } = await api.post("/fund", { amount }); setMsg(data.message); setAmount(""); refresh(); load(); }
    catch (err) { setError(apiError(err)); } finally { setLoading(false); }
  }

  const preview = amount ? Number(amount) * 2 : 0;

  return (
    <div className="space-y-6">
      <h1 className="text-2xl font-bold">Fund</h1>
      <div className="grid lg:grid-cols-2 gap-6">
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

        <div className="glass p-6">
          <h3 className="font-semibold mb-4">Fund History</h3>
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead className="text-gold-light text-left"><tr><th className="py-2">Amount</th><th>Return (200%)</th><th>Paid</th><th>Status</th><th>Date</th></tr></thead>
              <tbody>
                {history.map((p) => (
                  <tr key={p.id} className="border-t border-[var(--line)]">
                    <td className="py-2">{usdt(p.principal)}</td>
                    <td>{usdt(p.total_return)}</td>
                    <td className="text-gold-light">{usdt(p.total_paid)}</td>
                    <td><StatusPill status={p.status} /></td>
                    <td className="text-muted text-xs">{shortDate(p.created_at)}</td>
                  </tr>
                ))}
                {history.length === 0 && <tr><td colSpan={5} className="py-4 text-muted text-center">No fund packages yet.</td></tr>}
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  );
}
