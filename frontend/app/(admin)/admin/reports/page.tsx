"use client";

import { useState } from "react";
import { Download, CalendarRange, Users } from "lucide-react";
import Link from "next/link";
import api, { apiError } from "@/lib/api";

const REPORTS = [
  { type: "members", label: "Members" },
  { type: "deposits", label: "Deposits" },
  { type: "withdrawals", label: "Withdrawals" },
];

export default function AdminReports() {
  const [error, setError] = useState("");
  const [busy, setBusy] = useState("");
  const [parent, setParent] = useState("");

  async function download(type: string) {
    setError(""); setBusy(type);
    try {
      const res = await api.get(`/admin/reports/${type}`, { responseType: "blob" });
      const url = window.URL.createObjectURL(new Blob([res.data]));
      const a = document.createElement("a");
      a.href = url; a.download = `regal_${type}.csv`; a.click();
      window.URL.revokeObjectURL(url);
    } catch (err) { setError(apiError(err)); } finally { setBusy(""); }
  }

  async function downloadGroup() {
    const p = parent.trim();
    if (!p) { setError("Masukkan username parent (cth. KOMANDO)."); return; }
    setError(""); setBusy("group");
    try {
      const res = await api.get(`/admin/reports/group-deposits`, { params: { parent: p }, responseType: "blob" });
      const url = window.URL.createObjectURL(new Blob([res.data]));
      const a = document.createElement("a");
      a.href = url; a.download = `regal_group_deposits_${p}.csv`; a.click();
      window.URL.revokeObjectURL(url);
    } catch (err) { setError(apiError(err)); } finally { setBusy(""); }
  }

  return (
    <div className="space-y-6 max-w-2xl">
      <h1 className="text-2xl font-bold">Reports</h1>

      <div>
        <p className="text-muted text-sm mb-3">Printable monthly summary.</p>
        <Link href="/admin/reports/deposits" className="glass card-hover p-6 flex items-center gap-4">
          <span className="grid place-items-center w-11 h-11 rounded-xl gold-gradient text-black shrink-0"><CalendarRange size={18} /></span>
          <div>
            <p className="font-semibold">Monthly Deposit Report</p>
            <p className="text-xs text-muted mt-1">Deposit per bulan (borang + adjust admin + LD) — untuk print / semak.</p>
          </div>
        </Link>
      </div>

      <div>
        <p className="text-muted text-sm mb-3">Group deposit per bulan — download CSV.</p>
        <div className="glass p-6">
          <div className="flex items-center gap-3 mb-3">
            <span className="grid place-items-center w-11 h-11 rounded-xl gold-gradient text-black shrink-0"><Users size={18} /></span>
            <div>
              <p className="font-semibold">Group Deposit Report</p>
              <p className="text-xs text-muted mt-1">Masukkan username parent → setiap group direct di bawahnya, deposit setiap bulan.</p>
            </div>
          </div>
          <div className="flex flex-col sm:flex-row gap-3">
            <input className="input-field flex-1" placeholder="Username parent (cth. KOMANDO)" value={parent}
              onChange={(e) => setParent(e.target.value)} onKeyDown={(e) => e.key === "Enter" && downloadGroup()} />
            <button onClick={downloadGroup} disabled={busy === "group"} className="btn-gold px-5 py-2.5 flex items-center justify-center gap-2 shrink-0">
              <Download size={16} /> {busy === "group" ? "Preparing…" : "Download CSV"}
            </button>
          </div>
        </div>
      </div>

      <div>
        <p className="text-muted text-sm mb-3">Export platform data as CSV.</p>
        {error && <div className="text-sm text-red-400 bg-red-500/10 border border-red-500/30 rounded-lg px-4 py-3 mb-3">{error}</div>}
        <div className="grid sm:grid-cols-3 gap-4">
          {REPORTS.map((r) => (
            <button key={r.type} onClick={() => download(r.type)} disabled={busy === r.type}
              className="glass card-hover p-6 text-left">
              <span className="grid place-items-center w-11 h-11 rounded-xl gold-gradient text-black mb-3"><Download size={18} /></span>
              <p className="font-semibold">{r.label}</p>
              <p className="text-xs text-muted mt-1">{busy === r.type ? "Preparing…" : "Download CSV"}</p>
            </button>
          ))}
        </div>
      </div>
    </div>
  );
}
