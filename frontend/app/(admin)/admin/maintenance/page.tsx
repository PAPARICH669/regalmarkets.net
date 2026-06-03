"use client";

import { useEffect, useState } from "react";
import { Wrench } from "lucide-react";
import api, { apiError } from "@/lib/api";

interface Status { active: boolean; manual: boolean; window_start: string; window_end: string; server_time: string; }

export default function AdminMaintenance() {
  const [status, setStatus] = useState<Status | null>(null);
  const [error, setError] = useState("");

  const load = () => api.get("/admin/maintenance").then((r) => setStatus(r.data));
  useEffect(() => { load(); }, []);

  async function toggle(manual: boolean) {
    setError("");
    try { const { data } = await api.post("/admin/maintenance/toggle", { manual }); setStatus(data.status); }
    catch (err) { setError(apiError(err)); }
  }

  return (
    <div className="space-y-5 max-w-2xl">
      <h1 className="text-2xl font-bold">Maintenance Mode</h1>
      {error && <div className="text-sm text-red-400 bg-red-500/10 border border-red-500/30 rounded-lg px-4 py-3">{error}</div>}
      <div className="glass p-6">
        <div className="flex items-center gap-3">
          <span className="grid place-items-center w-12 h-12 rounded-xl gold-gradient text-black"><Wrench size={22} /></span>
          <div>
            <p className="font-semibold">{status?.active ? "System in maintenance" : "System live"}</p>
            <p className="text-sm text-muted">Scheduled window {status?.window_start}–06:59 (Asia/Kuala_Lumpur), live again at {status?.window_end}.</p>
          </div>
        </div>
        <div className="mt-6 flex gap-3">
          <button onClick={() => toggle(true)} className="btn-gold px-5 py-2.5">Force Maintenance ON</button>
          <button onClick={() => toggle(false)} className="btn-ghost px-5 py-2.5">Force Maintenance OFF</button>
        </div>
        <p className="mt-4 text-xs text-muted">Manual override: {status?.manual ? "ON" : "OFF"}. Turning off reverts to the daily schedule.</p>
      </div>
    </div>
  );
}
