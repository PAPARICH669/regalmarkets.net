"use client";

import { useEffect, useState } from "react";
import api, { apiError } from "@/lib/api";
import { usdt, shortDate } from "@/lib/format";
import StatusPill from "@/components/StatusPill";

interface Deposit { id: number; amount: string; txid: string | null; status: string; created_at: string; }

export default function DepositPage() {
  const [amount, setAmount] = useState("");
  const [txid, setTxid] = useState("");
  const [proof, setProof] = useState<File | null>(null);
  const [msg, setMsg] = useState(""); const [error, setError] = useState("");
  const [loading, setLoading] = useState(false);
  const [history, setHistory] = useState<Deposit[]>([]);

  const load = () => api.get("/deposits").then((r) => setHistory(r.data.data));
  useEffect(() => { load(); }, []);

  async function submit(e: React.FormEvent) {
    e.preventDefault();
    setMsg(""); setError(""); setLoading(true);
    try {
      const fd = new FormData();
      fd.append("amount", amount);
      if (txid) fd.append("txid", txid);
      if (proof) fd.append("proof", proof);
      const { data } = await api.post("/deposits", fd, { headers: { "Content-Type": "multipart/form-data" } });
      setMsg(data.message); setAmount(""); setTxid(""); setProof(null);
      load();
    } catch (err) { setError(apiError(err)); } finally { setLoading(false); }
  }

  return (
    <div className="space-y-6">
      <h1 className="text-2xl font-bold">Deposit USDT</h1>
      <div className="grid lg:grid-cols-2 gap-6">
        <div className="glass p-6">
          <h3 className="font-semibold">New Deposit</h3>
          <p className="text-sm text-muted mt-1">Funds are credited to your A-WALLET after admin approval and a 200% package is activated.</p>
          {msg && <div className="mt-4 text-sm text-green-400 bg-green-500/10 border border-green-500/30 rounded-lg px-4 py-3">{msg}</div>}
          {error && <div className="mt-4 text-sm text-red-400 bg-red-500/10 border border-red-500/30 rounded-lg px-4 py-3">{error}</div>}
          <form onSubmit={submit} className="mt-5 space-y-4">
            <div>
              <label className="text-sm text-muted">Amount (USDT)</label>
              <input type="number" step="0.01" min="10" className="input-field mt-1" value={amount} onChange={(e) => setAmount(e.target.value)} required />
            </div>
            <div>
              <label className="text-sm text-muted">Transaction Hash / TXID</label>
              <input className="input-field mt-1" value={txid} onChange={(e) => setTxid(e.target.value)} placeholder="On-chain TXID" />
            </div>
            <div>
              <label className="text-sm text-muted">Payment proof (image)</label>
              <input type="file" accept="image/*" className="input-field mt-1" onChange={(e) => setProof(e.target.files?.[0] || null)} />
            </div>
            <button disabled={loading} className="btn-gold w-full py-2.5">{loading ? "Submitting…" : "Submit Deposit"}</button>
          </form>
        </div>

        <div className="glass p-6">
          <h3 className="font-semibold mb-4">Deposit History</h3>
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead className="text-gold-light text-left"><tr><th className="py-2">Amount</th><th>TXID</th><th>Status</th><th>Date</th></tr></thead>
              <tbody>
                {history.map((d) => (
                  <tr key={d.id} className="border-t border-[var(--line)]">
                    <td className="py-2">{usdt(d.amount)}</td>
                    <td className="text-muted truncate max-w-[120px]">{d.txid || "—"}</td>
                    <td><StatusPill status={d.status} /></td>
                    <td className="text-muted text-xs">{shortDate(d.created_at)}</td>
                  </tr>
                ))}
                {history.length === 0 && <tr><td colSpan={4} className="py-4 text-muted text-center">No deposits yet.</td></tr>}
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  );
}
