"use client";

import { useEffect, useState } from "react";
import {
  Users, ArrowDownToLine, ArrowUpFromLine, AlertTriangle, Layers, Gift, Coins, Activity,
} from "lucide-react";
import StatCard from "@/components/StatCard";
import AdminRegisterLink from "@/components/AdminRegisterLink";
import api from "@/lib/api";
import { usdt } from "@/lib/format";

interface Stats {
  members: number; frozen_members: number; total_deposits: number; pending_deposits: number;
  total_withdrawals: number; pending_withdrawals: number; roi_liability: number;
  total_sponsor_bonus: number; total_matching_bonus: number; wallet_a_total: number;
  wallet_e_total: number; active_packages: number;
}

export default function AdminOverview() {
  const [s, setS] = useState<Stats | null>(null);
  useEffect(() => {
    const load = () => api.get("/admin/dashboard").then((r) => setS(r.data)).catch(() => {});
    load();
    // Re-fetch whenever the admin returns to this tab, so totals (E-Wallet, etc.)
    // always reflect the latest — e.g. right after a manual wallet adjustment.
    const onFocus = () => { if (document.visibilityState === "visible") load(); };
    window.addEventListener("focus", onFocus);
    document.addEventListener("visibilitychange", onFocus);
    return () => { window.removeEventListener("focus", onFocus); document.removeEventListener("visibilitychange", onFocus); };
  }, []);
  if (!s) return <div className="text-muted py-10">Loading…</div>;

  return (
    <div className="space-y-6">
      <h1 className="text-2xl font-bold">Admin Overview</h1>
      <AdminRegisterLink />
      <div className="grid sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <StatCard label="Total Members" value={s.members} icon={<Users size={18} />} accent sub={`${s.frozen_members} frozen`} />
        <StatCard label="Total Deposits" value={usdt(s.total_deposits)} icon={<ArrowDownToLine size={18} />} sub={`${s.pending_deposits} pending`} />
        <StatCard label="Total Withdrawals" value={usdt(s.total_withdrawals)} icon={<ArrowUpFromLine size={18} />} sub={`${s.pending_withdrawals} pending`} />
        <StatCard label="Commission Liability" value={usdt(s.roi_liability)} icon={<AlertTriangle size={18} />} accent sub={`${s.active_packages} active packages`} />
      </div>
      <div className="grid sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <StatCard label="Sponsor Bonus Paid" value={usdt(s.total_sponsor_bonus)} icon={<Gift size={18} />} />
        <StatCard label="Matching Bonus Paid" value={usdt(s.total_matching_bonus)} icon={<Layers size={18} />} />
        <StatCard label="A-WALLET Total" value={usdt(s.wallet_a_total)} icon={<Coins size={18} />} />
        <StatCard label="E-WALLET Total" value={usdt(s.wallet_e_total)} icon={<Activity size={18} />} />
      </div>
    </div>
  );
}
