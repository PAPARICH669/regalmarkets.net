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
        withdrawal_fee: Number(s?.withdrawal_fee),
        withdrawal_max_per_day: Number(s?.withdrawal_max_per_day),
        withdrawal_window_start: String(s?.withdrawal_window_start ?? "09:00"),
        withdrawal_window_end: String(s?.withdrawal_window_end ?? "12:00"),
        maintenance_start: String(s?.maintenance_start ?? "00:00"),
        maintenance_end: String(s?.maintenance_end ?? "07:00"),
        login_tac_enabled: !!s?.login_tac_enabled,
        total_deposit_adjustment: Number(s?.total_deposit_adjustment ?? 0),
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
    ["withdrawal_window_start", "Withdrawal Window Start (HH:MM)", "text"],
    ["withdrawal_window_end", "Withdrawal Window End (HH:MM)", "text"],
    ["min_deposit", "Min Deposit", "number"],
    ["min_withdrawal", "Min Withdrawal", "number"],
    ["max_withdrawal_daily", "Max per Withdrawal", "number"],
    ["maintenance_start", "Maintenance Start (HH:MM)", "text"],
    ["maintenance_end", "Maintenance End (HH:MM)", "text"],
    ["total_deposit_adjustment", "Total Deposit Deduction (USDT)", "number"],
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
        <div className="sm:col-span-2 border-t border-[var(--line)] pt-4">
          <label className="flex items-center gap-3 cursor-pointer">
            <input type="checkbox" className="w-4 h-4 accent-[var(--gold)]"
              checked={!!s.login_tac_enabled} onChange={(e) => setS((p) => ({ ...(p || {}), login_tac_enabled: e.target.checked }))} />
            <span className="text-sm font-medium">Require email login code (TAC / 2FA) — admin &amp; staff</span>
          </label>
          <p className="text-xs text-muted mt-1 ml-7">When on, <b>admins and staff</b> must enter a 6-digit code emailed to them at login. Regular members log in without a code. Keep on for best security.</p>
        </div>
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
