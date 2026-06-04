"use client";

import { useEffect, useState } from "react";
import api, { apiError } from "@/lib/api";

export default function AdminSettings() {
  const [s, setS] = useState<Record<string, unknown> | null>(null);
  const [msg, setMsg] = useState(""); const [error, setError] = useState("");
  const [saving, setSaving] = useState(false);

  useEffect(() => { api.get("/admin/settings").then((r) => setS(r.data)); }, []);

  function field(key: string, value: string) { setS((prev) => ({ ...(prev || {}), [key]: value })); }

  async function save(e: React.FormEvent) {
    e.preventDefault(); setMsg(""); setError(""); setSaving(true);
    try {
      const payload = {
        roi_daily_percent: Number(s?.roi_daily_percent),
        roi_return_multiple: Number(s?.roi_return_multiple),
        min_deposit: Number(s?.min_deposit),
        min_withdrawal: Number(s?.min_withdrawal),
        max_withdrawal_daily: Number(s?.max_withdrawal_daily),
        min_transfer: Number(s?.min_transfer),
        min_reinvest: Number(s?.min_reinvest),
        withdrawal_fee: Number(s?.withdrawal_fee),
        withdrawal_max_per_day: Number(s?.withdrawal_max_per_day),
        transfer_fee_percent: Number(s?.transfer_fee_percent),
        maintenance_start: String(s?.maintenance_start ?? "00:00"),
        maintenance_end: String(s?.maintenance_end ?? "07:00"),
      };
      const { data } = await api.put("/admin/settings", payload);
      setS(data.settings); setMsg("Settings saved.");
    } catch (err) { setError(apiError(err)); } finally { setSaving(false); }
  }

  if (!s) return <div className="text-muted py-10">Loading…</div>;

  const fields: [string, string, string][] = [
    ["roi_daily_percent", "Daily Commission %", "number"],
    ["roi_return_multiple", "Return Multiple (x)", "number"],
    ["withdrawal_fee", "Withdrawal Fee (USDT flat)", "number"],
    ["withdrawal_max_per_day", "Withdrawals per day", "number"],
    ["transfer_fee_percent", "Transfer Fee E→A (%)", "number"],
    ["min_deposit", "Min Deposit", "number"],
    ["min_withdrawal", "Min Withdrawal", "number"],
    ["max_withdrawal_daily", "Max per Withdrawal", "number"],
    ["min_transfer", "Min Transfer", "number"],
    ["min_reinvest", "Min Reinvest", "number"],
    ["maintenance_start", "Maintenance Start (HH:MM)", "text"],
    ["maintenance_end", "Maintenance End (HH:MM)", "text"],
  ];

  return (
    <div className="space-y-5 max-w-3xl">
      <h1 className="text-2xl font-bold">System Settings</h1>
      {msg && <div className="text-sm text-green-400 bg-green-500/10 border border-green-500/30 rounded-lg px-4 py-3">{msg}</div>}
      {error && <div className="text-sm text-red-400 bg-red-500/10 border border-red-500/30 rounded-lg px-4 py-3">{error}</div>}
      <form onSubmit={save} className="glass p-6 grid sm:grid-cols-2 gap-4">
        {fields.map(([key, label, type]) => (
          <div key={key}>
            <label className="text-sm text-muted">{label}</label>
            <input type={type} step="any" className="input-field mt-1"
              value={String(s[key] ?? "")} onChange={(e) => field(key, e.target.value)} />
          </div>
        ))}
        <div className="sm:col-span-2 text-xs text-muted">
          Sponsor %: {JSON.stringify(s.sponsor_bonus_percents)} · Matching %: {JSON.stringify(s.match_percents)}
        </div>
        <div className="sm:col-span-2">
          <button disabled={saving} className="btn-gold px-6 py-2.5">{saving ? "Saving…" : "Save Settings"}</button>
        </div>
      </form>
    </div>
  );
}
