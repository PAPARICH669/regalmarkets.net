"use client";

import { useEffect, useState } from "react";
import api, { apiError } from "@/lib/api";
import { usdt, shortDate } from "@/lib/format";

interface Adj {
  id: number; user_id: number; wallet_type: string; direction: string;
  amount: string; note: string | null; created_at: string;
  user?: { id: number; username: string };
}

export default function AdminWalletAdjustments() {
  const [items, setItems] = useState<Adj[]>([]);
  const [error, setError] = useState("");

  useEffect(() => { api.get("/admin/wallet-adjustments").then((r) => setItems(r.data)).catch((e) => setError(apiError(e))); }, []);

  const totalCredit = items.filter((i) => i.direction === "credit").reduce((s, i) => s + Number(i.amount), 0);
  const totalDebit = items.filter((i) => i.direction === "debit").reduce((s, i) => s + Number(i.amount), 0);

  return (
    <div className="space-y-5">
      <h1 className="text-2xl font-bold">Wallet Adjustments</h1>
      <p className="text-sm text-muted">History of manual admin credit/debit on member wallets — shows member ID, wallet, and amount adjusted.</p>
      {error && <div className="text-sm text-red-400 bg-red-500/10 border border-red-500/30 rounded-lg px-4 py-3">{error}</div>}

      <div className="grid grid-cols-2 gap-3 max-w-md">
        <div className="glass p-4"><p className="text-xs text-muted">Total credited</p><p className="text-lg font-bold text-green-400">{usdt(totalCredit)}</p></div>
        <div className="glass p-4"><p className="text-xs text-muted">Total debited</p><p className="text-lg font-bold text-red-400">{usdt(totalDebit)}</p></div>
      </div>

      <div className="glass overflow-x-auto">
        <table className="w-full text-sm">
          <thead className="bg-white/5 text-gold-light text-left">
            <tr><th className="px-4 py-3">Member (ID)</th><th>Wallet</th><th>Type</th><th>Amount</th><th>Note</th><th>Date</th></tr>
          </thead>
          <tbody>
            {items.map((a) => (
              <tr key={a.id} className="border-t border-[var(--line)]">
                <td className="px-4 py-3">@{a.user?.username || "—"} <span className="text-xs text-muted">· ID {a.user_id}</span></td>
                <td>{a.wallet_type}-WALLET</td>
                <td>{a.direction === "credit"
                  ? <span className="text-xs text-green-400">credit</span>
                  : <span className="text-xs text-red-400">debit</span>}</td>
                <td className={a.direction === "credit" ? "text-green-400 font-medium" : "text-red-400 font-medium"}>
                  {a.direction === "credit" ? "+" : "−"}{usdt(a.amount)}
                </td>
                <td className="text-muted text-xs max-w-[160px] truncate" title={a.note || ""}>{a.note || "—"}</td>
                <td className="text-muted text-xs whitespace-nowrap">{shortDate(a.created_at)}</td>
              </tr>
            ))}
            {items.length === 0 && <tr><td colSpan={6} className="py-6 text-center text-muted">No wallet adjustments yet.</td></tr>}
          </tbody>
        </table>
      </div>
    </div>
  );
}
