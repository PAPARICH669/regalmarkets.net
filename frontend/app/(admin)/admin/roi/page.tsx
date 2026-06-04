"use client";

import { useEffect, useState } from "react";
import { TrendingUp, Play, AlertTriangle, Layers } from "lucide-react";
import StatCard from "@/components/StatCard";
import api, { apiError } from "@/lib/api";
import { usdt } from "@/lib/format";

interface Status {
  default_percent: number;
  active_packages: number;
  active_principal: number;
  roi_liability: number;
  today: string;
  today_paid: number;
  today_paid_count: number;
}

export default function AdminRoiPage() {
  const [s, setS] = useState<Status | null>(null);
  const [percent, setPercent] = useState("1");
  const [saveDefault, setSaveDefault] = useState(true);
  const [msg, setMsg] = useState(""); const [error, setError] = useState("");
  const [running, setRunning] = useState(false);

  const load = () => api.get("/admin/roi/status").then((r) => { setS(r.data); setPercent(String(r.data.default_percent)); });
  useEffect(() => { load(); }, []);

  const estimate = s ? (Number(percent || 0) / 100) * s.active_principal : 0;

  async function run() {
    if (!window.confirm(`Run Commission at ${percent}% for today? This pays ${usdt(estimate)} across ${s?.active_packages} packages and cannot be undone.`)) return;
    setMsg(""); setError(""); setRunning(true);
    try {
      const { data } = await api.post("/admin/roi/run", { percent: Number(percent), save_default: saveDefault });
      setMsg(`${data.message} Paid ${data.stats.paid} package(s), total ${Number(data.stats.amount).toFixed(2)} USDT.`);
      load();
    } catch (err) { setError(apiError(err)); } finally { setRunning(false); }
  }

  return (
    <div className="space-y-6 max-w-3xl">
      <div>
        <h1 className="text-2xl font-bold">Daily Commission Payout</h1>
        <p className="text-muted text-sm">Set the daily profit % manually and run the payout. Profit = % × each member&apos;s investment; remaining Commission shrinks until 200%.</p>
      </div>

      <div className="grid sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <StatCard label="Active Packages" value={s?.active_packages ?? 0} icon={<Layers size={18} />} />
        <StatCard label="Active Principal" value={usdt(s?.active_principal ?? 0)} icon={<TrendingUp size={18} />} accent />
        <StatCard label="Commission Liability" value={usdt(s?.roi_liability ?? 0)} icon={<AlertTriangle size={18} />} />
        <StatCard label="Paid Today" value={usdt(s?.today_paid ?? 0)} icon={<TrendingUp size={18} />} sub={`${s?.today_paid_count ?? 0} payouts`} />
      </div>

      {msg && <div className="text-sm text-green-400 bg-green-500/10 border border-green-500/30 rounded-lg px-4 py-3">{msg}</div>}
      {error && <div className="text-sm text-red-400 bg-red-500/10 border border-red-500/30 rounded-lg px-4 py-3">{error}</div>}

      <div className="glass p-6 space-y-4">
        <div className="grid sm:grid-cols-2 gap-4">
          <div>
            <label className="text-sm text-muted">Daily profit % (manual)</label>
            <input type="number" step="0.01" min="0" max="100" className="input-field mt-1" value={percent} onChange={(e) => setPercent(e.target.value)} />
          </div>
          <div className="flex items-end">
            <div className="bg-black/30 rounded-lg p-3 w-full text-sm">
              <div className="flex justify-between"><span className="text-muted">Est. payout today</span><span className="gold-text font-semibold">{usdt(estimate)}</span></div>
              <div className="text-xs text-muted mt-1">{percent || 0}% × {usdt(s?.active_principal ?? 0)} active principal</div>
            </div>
          </div>
        </div>

        <label className="flex items-center gap-2 text-sm text-muted">
          <input type="checkbox" checked={saveDefault} onChange={(e) => setSaveDefault(e.target.checked)} />
          Save this % as the default rate (used by the daily 07:05 auto-run)
        </label>

        <button onClick={run} disabled={running} className="btn-gold px-6 py-2.5 flex items-center gap-2">
          <Play size={16} /> {running ? "Running…" : "Run Commission for Today"}
        </button>
        <p className="text-xs text-muted">Commission is idempotent per package per day — running again today won&apos;t double-pay packages already paid.</p>
      </div>
    </div>
  );
}
