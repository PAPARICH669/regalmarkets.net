"use client";

import { useEffect, useState } from "react";
import { Printer, ArrowLeft } from "lucide-react";
import Link from "next/link";
import api, { apiError } from "@/lib/api";

interface Row {
  month: string; form_count: number; form: number;
  adjust: number; adjust_count: number; ld: number; ld_count: number; total: number;
}
interface Report {
  rows: Row[]; grand_total: number; form_total: number; adjust_total: number;
  ld_total: number; adjustment_setting: number; generated_at: string;
}

const BULAN = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];
const monthLabel = (ym: string) => {
  const [y, m] = ym.split("-");
  return `${BULAN[Number(m) - 1] ?? m} ${y}`;
};
const fmt = (n: number) => Number(n).toLocaleString("en-US", { minimumFractionDigits: 2, maximumFractionDigits: 2 });

export default function DepositMonthlyReport() {
  const [data, setData] = useState<Report | null>(null);
  const [error, setError] = useState("");

  useEffect(() => {
    api.get("/admin/reports/deposits-monthly").then((r) => setData(r.data)).catch((e) => setError(apiError(e)));
  }, []);

  return (
    <div className="space-y-5">
      <div className="flex items-center justify-between flex-wrap gap-3 print:hidden">
        <div className="flex items-center gap-3">
          <Link href="/admin/reports" className="btn-ghost p-2"><ArrowLeft size={16} /></Link>
          <h1 className="text-2xl font-bold">Monthly Deposit Report</h1>
        </div>
        <button onClick={() => window.print()} className="btn-gold px-4 py-2 flex items-center gap-2"><Printer size={16} /> Print / Save PDF</button>
      </div>

      {error && <div className="text-sm text-red-400 bg-red-500/10 border border-red-500/30 rounded-lg px-4 py-3 print:hidden">{error}</div>}

      {/* Print header — only meaningful on paper */}
      <div className="hidden print:block mb-4">
        <h1 className="text-xl font-bold">Regal Markets — Monthly Deposit Report</h1>
        {data && <p className="text-sm">Generated: {data.generated_at} · Currency: USDT · Dummy accounts excluded</p>}
      </div>

      <div className="glass p-4 sm:p-6 overflow-x-auto print:bg-white print:text-black">
        <table className="w-full text-sm">
          <thead>
            <tr className="text-left text-gold-light print:text-black border-b border-[var(--line)]">
              <th className="py-2 pr-3">Month</th>
              <th className="py-2 px-3 text-right">Form deposit</th>
              <th className="py-2 px-3 text-right">Admin adjust</th>
              <th className="py-2 px-3 text-right">LD transfer</th>
              <th className="py-2 pl-3 text-right">Total (USDT)</th>
            </tr>
          </thead>
          <tbody>
            {data?.rows.map((r) => (
              <tr key={r.month} className="border-b border-[var(--line)]/60">
                <td className="py-2 pr-3 font-medium whitespace-nowrap">{monthLabel(r.month)}</td>
                <td className="py-2 px-3 text-right">{fmt(r.form)} <span className="text-muted text-xs">({r.form_count})</span></td>
                <td className="py-2 px-3 text-right">{fmt(r.adjust)} <span className="text-muted text-xs">({r.adjust_count})</span></td>
                <td className="py-2 px-3 text-right">{fmt(r.ld)} <span className="text-muted text-xs">({r.ld_count})</span></td>
                <td className="py-2 pl-3 text-right font-semibold">{fmt(r.total)}</td>
              </tr>
            ))}
            {data && data.rows.length === 0 && <tr><td colSpan={5} className="py-6 text-center text-muted">No deposits yet.</td></tr>}
          </tbody>
          {data && data.rows.length > 0 && (
            <tfoot>
              <tr className="border-t-2 border-[var(--line)] font-bold">
                <td className="py-3 pr-3">TOTAL</td>
                <td className="py-3 px-3 text-right">{fmt(data.form_total)}</td>
                <td className="py-3 px-3 text-right">{fmt(data.adjust_total)}</td>
                <td className="py-3 px-3 text-right">{fmt(data.ld_total)}</td>
                <td className="py-3 pl-3 text-right text-gold-light print:text-black">{fmt(data.grand_total)}</td>
              </tr>
            </tfoot>
          )}
        </table>
      </div>

      {data && (
        <div className="text-xs text-muted space-y-1 print:text-black">
          <p>• <b>Form deposit</b> = approved USDT deposits (most transparent, verifiable on-chain).</p>
          <p>• <b>Admin adjust</b> = admin manual credit to A-Wallet. <b>LD transfer</b> = credit from LD-Wallet to member.</p>
          {data.adjustment_setting > 0 && (
            <p>• Note: there is a <b>{fmt(data.adjustment_setting)} USDT</b> adjustment (seed/non-deposit) deducted on the dashboard Total Deposit display, not deducted in this table.</p>
          )}
        </div>
      )}
    </div>
  );
}
