"use client";

import { useState } from "react";
import { Download } from "lucide-react";
import api, { apiError } from "@/lib/api";

const REPORTS = [
  { type: "members", label: "Members" },
  { type: "deposits", label: "Deposits" },
  { type: "withdrawals", label: "Withdrawals" },
];

export default function AdminReports() {
  const [error, setError] = useState("");
  const [busy, setBusy] = useState("");

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

  return (
    <div className="space-y-5 max-w-2xl">
      <h1 className="text-2xl font-bold">Reports</h1>
      <p className="text-muted text-sm">Export platform data as CSV.</p>
      {error && <div className="text-sm text-red-400 bg-red-500/10 border border-red-500/30 rounded-lg px-4 py-3">{error}</div>}
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
  );
}
