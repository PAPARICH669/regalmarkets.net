"use client";

import { useEffect, useState } from "react";
import api, { apiError } from "@/lib/api";
import { usdt, shortDate } from "@/lib/format";

interface Reward {
  id: number; period: string; position: number; score: string; amount: string;
  status: string; paid_at: string | null; user?: { id: number; username: string; email: string };
}

const MEDAL: Record<number, string> = { 1: "🥇", 2: "🥈", 3: "🥉", 4: "🏅", 5: "🏅" };

export default function AdminSponsorRewards() {
  const [items, setItems] = useState<Reward[]>([]);
  const [msg, setMsg] = useState(""); const [error, setError] = useState("");
  const [busy, setBusy] = useState(false);

  const load = () => api.get("/admin/sponsor-rewards").then((r) => setItems(r.data)).catch((e) => setError(apiError(e)));
  useEffect(() => { load(); }, []);

  async function generate() {
    const period = window.prompt("Generate rewards for which month? (YYYY-MM, leave blank = previous month)") ?? "";
    setMsg(""); setError(""); setBusy(true);
    try {
      const { data } = await api.post("/admin/sponsor-rewards/generate", period ? { period } : {});
      setMsg(data.message); load();
    } catch (e) { setError(apiError(e)); } finally { setBusy(false); }
  }

  async function settle(r: Reward) {
    if (!window.confirm(`Settle Top ${r.position} reward of ${usdt(r.amount)} to ${r.user?.username}?\nThis credits their E-WALLET and emails them.`)) return;
    setMsg(""); setError("");
    try {
      const { data } = await api.post(`/admin/sponsor-rewards/${r.id}/settle`);
      setMsg(data.message); load();
    } catch (e) { setError(apiError(e)); }
  }

  const pending = items.filter((i) => i.status === "pending");

  return (
    <div className="space-y-5">
      <div className="flex items-center justify-between flex-wrap gap-3">
        <h1 className="text-2xl font-bold">Top 5 Sponsor Rewards</h1>
        <button onClick={generate} disabled={busy} className="btn-ghost px-4 py-2 text-sm">{busy ? "Generating…" : "Generate month"}</button>
      </div>
      <p className="text-sm text-muted">Monthly Top-5 sponsors (by new invest from direct downlines). <b className="text-gold-light">{pending.length}</b> reward(s) awaiting settlement. Settling credits the member&apos;s E-WALLET and emails them.</p>
      {msg && <div className="text-sm text-green-400 bg-green-500/10 border border-green-500/30 rounded-lg px-4 py-3">{msg}</div>}
      {error && <div className="text-sm text-red-400 bg-red-500/10 border border-red-500/30 rounded-lg px-4 py-3">{error}</div>}

      <div className="glass overflow-x-auto">
        <table className="w-full text-sm">
          <thead className="bg-white/5 text-gold-light text-left">
            <tr><th className="px-4 py-3">Month</th><th>Pos</th><th>Member</th><th>Monthly invest</th><th>Reward</th><th>Status</th><th>Actions</th></tr>
          </thead>
          <tbody>
            {items.map((r) => (
              <tr key={r.id} className="border-t border-[var(--line)]">
                <td className="px-4 py-3 whitespace-nowrap">{r.period}</td>
                <td>{MEDAL[r.position] ?? "#"} {r.position}</td>
                <td>{r.user?.username || "—"}<div className="text-xs text-muted break-all">{r.user?.email}</div></td>
                <td>{usdt(r.score)}</td>
                <td className="gold-text font-bold">{usdt(r.amount)}</td>
                <td>
                  {r.status === "paid"
                    ? <span className="text-xs text-green-400">Paid {r.paid_at ? `· ${shortDate(r.paid_at)}` : ""}</span>
                    : <span className="text-xs text-yellow-400">Pending</span>}
                </td>
                <td>
                  {r.status === "pending"
                    ? <button onClick={() => settle(r)} className="px-2.5 py-1 text-xs rounded bg-green-500/15 text-green-400 hover:bg-green-500/25">Settle</button>
                    : <span className="text-xs text-muted">—</span>}
                </td>
              </tr>
            ))}
            {items.length === 0 && <tr><td colSpan={7} className="py-6 text-center text-muted">No rewards yet. Use “Generate month” after a month closes.</td></tr>}
          </tbody>
        </table>
      </div>
    </div>
  );
}
