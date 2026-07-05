"use client";

import { useEffect, useState } from "react";
import api from "@/lib/api";
import { usdt, shortDate } from "@/lib/format";
import StatusPill from "@/components/StatusPill";
import { useAuth } from "@/lib/auth";
import { ArrowUpCircle, Repeat, ArrowLeftRight } from "lucide-react";

interface Withdrawal { id: number; amount: string; fee: string; net_amount: string; status: string; created_at: string; }
interface Pkg { id: number; principal: string; total_return: string; total_paid: string; status: string; created_at: string; }
interface Transfer { id: number; direction: string; amount: string; note: string | null; to_username: string | null; created_at: string; }

type Tab = "withdrawal" | "fund" | "transfer";

export default function ActivityPage() {
  const user = useAuth((s) => s.user);
  const isLd = !!user?.is_ld;
  const [tab, setTab] = useState<Tab>("withdrawal");
  const [wd, setWd] = useState<Withdrawal[]>([]);
  const [fund, setFund] = useState<Pkg[]>([]);
  const [tf, setTf] = useState<Transfer[]>([]);
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    setLoading(true);
    const done = () => setLoading(false);
    if (tab === "withdrawal") api.get("/withdrawals").then((r) => setWd(r.data.data)).catch(() => {}).finally(done);
    else if (tab === "fund") api.get("/fund").then((r) => setFund(r.data.data)).catch(() => {}).finally(done);
    else api.get("/ld/wallet-history").then((r) => setTf(r.data)).catch(() => {}).finally(done);
  }, [tab]);

  const tabs: { key: Tab; label: string; icon: React.ReactNode }[] = [
    { key: "withdrawal", label: "Withdrawal", icon: <ArrowUpCircle size={15} /> },
    { key: "fund", label: "Fund", icon: <Repeat size={15} /> },
    ...(isLd ? [{ key: "transfer" as Tab, label: "Transfer", icon: <ArrowLeftRight size={15} /> }] : []),
  ];

  return (
    <div className="space-y-5">
      <div>
        <h1 className="text-2xl font-bold">Activity</h1>
        <p className="text-muted text-sm">Your withdrawal, fund{isLd ? ", and transfer" : ""} history.</p>
      </div>

      <div className="flex gap-2 flex-wrap">
        {tabs.map((t) => (
          <button key={t.key} onClick={() => setTab(t.key)}
            className={`px-4 py-2 rounded-lg text-sm flex items-center gap-1.5 ${tab === t.key ? "gold-gradient text-black font-semibold" : "btn-ghost"}`}>
            {t.icon} {t.label}
          </button>
        ))}
      </div>

      <div className="glass overflow-x-auto">
        {loading ? (
          <p className="p-6 text-center text-muted">Loading…</p>
        ) : tab === "withdrawal" ? (
          <table className="w-full text-sm">
            <thead className="bg-white/5 text-gold-light text-left"><tr><th className="px-4 py-3">Amount</th><th>Fee</th><th>Net</th><th>Status</th><th>Date</th></tr></thead>
            <tbody>
              {wd.map((w) => (
                <tr key={w.id} className="border-t border-[var(--line)]">
                  <td className="px-4 py-3">{usdt(w.amount)}</td><td>{usdt(w.fee)}</td>
                  <td className="text-gold-light">{usdt(w.net_amount)}</td><td><StatusPill status={w.status} /></td>
                  <td className="text-xs text-muted whitespace-nowrap">{shortDate(w.created_at)}</td>
                </tr>
              ))}
              {wd.length === 0 && <tr><td colSpan={5} className="py-6 text-center text-muted">No withdrawals yet.</td></tr>}
            </tbody>
          </table>
        ) : tab === "fund" ? (
          <table className="w-full text-sm">
            <thead className="bg-white/5 text-gold-light text-left"><tr><th className="px-4 py-3">Amount</th><th>Return (200%)</th><th>Paid</th><th>Status</th><th>Date</th></tr></thead>
            <tbody>
              {fund.map((p) => (
                <tr key={p.id} className="border-t border-[var(--line)]">
                  <td className="px-4 py-3">{usdt(p.principal)}</td><td>{usdt(p.total_return)}</td>
                  <td className="text-gold-light">{usdt(p.total_paid)}</td><td><StatusPill status={p.status} /></td>
                  <td className="text-xs text-muted whitespace-nowrap">{shortDate(p.created_at)}</td>
                </tr>
              ))}
              {fund.length === 0 && <tr><td colSpan={5} className="py-6 text-center text-muted">No fund packages yet.</td></tr>}
            </tbody>
          </table>
        ) : (
          <table className="w-full text-sm">
            <thead className="bg-white/5 text-gold-light text-left"><tr><th className="px-4 py-3">Direction</th><th>Amount</th><th>To / Note</th><th>Date</th></tr></thead>
            <tbody>
              {tf.map((t) => (
                <tr key={t.id} className="border-t border-[var(--line)]">
                  <td className="px-4 py-3 capitalize">{t.direction}</td>
                  <td className="text-gold-light">{usdt(t.amount)}</td>
                  <td className="text-muted break-all">{t.to_username ? `@${t.to_username}` : (t.note || "—")}</td>
                  <td className="text-xs text-muted whitespace-nowrap">{shortDate(t.created_at)}</td>
                </tr>
              ))}
              {tf.length === 0 && <tr><td colSpan={4} className="py-6 text-center text-muted">No transfers yet.</td></tr>}
            </tbody>
          </table>
        )}
      </div>
    </div>
  );
}
