"use client";

import { useEffect, useState } from "react";
import api from "@/lib/api";
import { usdt, shortDate } from "@/lib/format";

type Tab = "matching" | "sponsor" | "audit";

export default function AdminLogs() {
  const [tab, setTab] = useState<Tab>("matching");
  const [rows, setRows] = useState<Record<string, unknown>[]>([]);

  useEffect(() => { api.get(`/admin/logs/${tab}`).then((r) => setRows(r.data.data)); }, [tab]);

  return (
    <div className="space-y-5">
      <h1 className="text-2xl font-bold">Bonus & Audit Logs</h1>
      <div className="flex gap-2">
        {(["matching", "sponsor", "audit"] as Tab[]).map((t) => (
          <button key={t} onClick={() => setTab(t)} className={`px-4 py-2 rounded-lg text-sm capitalize ${tab === t ? "gold-gradient text-black font-semibold" : "btn-ghost"}`}>{t}</button>
        ))}
      </div>
      <div className="glass overflow-x-auto">
        <table className="w-full text-sm">
          {tab === "matching" && (
            <>
              <thead className="bg-white/5 text-gold-light text-left"><tr><th className="px-4 py-3">From</th><th>To</th><th>Rank</th><th>%</th><th>Amount</th><th>Date</th></tr></thead>
              <tbody>
                {rows.map((m) => (
                  <tr key={m.id as number} className="border-t border-[var(--line)]">
                    <td className="px-4 py-3">{(m.from_user as { username: string })?.username}</td>
                    <td>{(m.to_user as { username: string })?.username}</td>
                    <td className="text-xs text-muted">{m.upline_rank as string}</td>
                    <td>{Number(m.applied_percent)}%</td>
                    <td className="gold-text">{usdt(m.amount as string)}</td>
                    <td className="text-xs text-muted">{shortDate(m.created_at as string)}</td>
                  </tr>
                ))}
              </tbody>
            </>
          )}
          {tab === "sponsor" && (
            <>
              <thead className="bg-white/5 text-gold-light text-left"><tr><th className="px-4 py-3">From</th><th>To</th><th>Level</th><th>%</th><th>Amount</th><th>Date</th></tr></thead>
              <tbody>
                {rows.map((m) => (
                  <tr key={m.id as number} className="border-t border-[var(--line)]">
                    <td className="px-4 py-3">{(m.from_user as { username: string })?.username}</td>
                    <td>{(m.to_user as { username: string })?.username}</td>
                    <td>L{m.level as number}</td>
                    <td>{Number(m.percent)}%</td>
                    <td className="gold-text">{usdt(m.amount as string)}</td>
                    <td className="text-xs text-muted">{shortDate(m.created_at as string)}</td>
                  </tr>
                ))}
              </tbody>
            </>
          )}
          {tab === "audit" && (
            <>
              <thead className="bg-white/5 text-gold-light text-left"><tr><th className="px-4 py-3">Admin</th><th>Action</th><th>IP</th><th>Date</th></tr></thead>
              <tbody>
                {rows.map((m) => (
                  <tr key={m.id as number} className="border-t border-[var(--line)]">
                    <td className="px-4 py-3">{(m.user as { username: string })?.username ?? "—"}</td>
                    <td className="text-xs">{m.action as string}</td>
                    <td className="text-xs text-muted">{m.ip as string}</td>
                    <td className="text-xs text-muted">{shortDate(m.created_at as string)}</td>
                  </tr>
                ))}
              </tbody>
            </>
          )}
        </table>
        {rows.length === 0 && <p className="py-6 text-center text-muted">No records.</p>}
      </div>
    </div>
  );
}
