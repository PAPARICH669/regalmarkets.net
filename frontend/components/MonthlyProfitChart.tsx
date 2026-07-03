"use client";

import { useEffect, useState } from "react";
import api from "@/lib/api";

interface M {
  label: string;
  value: number;
  current: boolean;
  future?: boolean;
}

/**
 * Monthly profit bar chart on the member dashboard. Each bar = that month's own
 * total passive profit % (Jun 2026 → Dec 2026). The current month's bar grows
 * every day as the daily ROI is distributed, filling up to ~a full month by
 * month-end. Future months render as empty placeholders. Platform-wide data
 * from /monthly-profit — identical for every member. Lightweight, no chart lib.
 */
export default function MonthlyProfitChart() {
  const [months, setMonths] = useState<M[]>([]);

  useEffect(() => {
    api.get("/monthly-profit").then((r) => setMonths(r.data.months || [])).catch(() => {});
  }, []);

  if (months.length === 0) return null;

  const max = Math.max(...months.map((m) => m.value), 1);

  return (
    <div className="glass p-5">
      <div>
        <h3 className="font-semibold text-foreground">Monthly Profit — Regal Markets</h3>
        <p className="text-xs text-muted mt-0.5">Each bar = that month&apos;s profit (%) · grows daily · since Jun 2026</p>
      </div>

      <div className="mt-5 flex items-end gap-2 sm:gap-3" style={{ height: 180 }}>
        {months.map((m) => {
          const h = m.value > 0 ? Math.max((m.value / max) * 135, 5) : m.future ? 6 : 4;
          const bg = m.future
            ? "rgba(255,255,255,0.06)"
            : m.current
            ? "linear-gradient(180deg,#f2d98a,#c9a227)"
            : "linear-gradient(180deg,#e7c873,#c9a227)";
          return (
            <div key={m.label} className="flex-1 min-w-0 flex flex-col items-center justify-end h-full gap-1.5">
              <span className={`text-[10px] whitespace-nowrap ${m.current ? "font-semibold text-gold-light" : "text-gold-light"}`}>
                {m.value > 0 ? `${m.value.toFixed(1)}%` : ""}
              </span>
              <div
                className="w-full rounded-t-md transition-all"
                style={{ height: `${h}px`, background: bg }}
                title={
                  m.future
                    ? `${m.label}: belum bermula`
                    : `${m.label}: ${m.value.toFixed(1)}%${m.current ? " (bulan semasa — naik setiap hari)" : ""}`
                }
              />
              <span className={`text-[10px] whitespace-nowrap ${m.future ? "text-muted/50" : "text-muted"}`}>{m.label}</span>
            </div>
          );
        })}
      </div>

      <div className="mt-3 flex items-center gap-4 flex-wrap text-[10px] text-muted">
        <span className="flex items-center gap-1.5"><span className="w-2.5 h-2.5 rounded-sm" style={{ background: "#e7c873" }} /> Selesai</span>
        <span className="flex items-center gap-1.5"><span className="w-2.5 h-2.5 rounded-sm" style={{ background: "#f2d98a" }} /> Bulan semasa (naik harian)</span>
        <span className="flex items-center gap-1.5"><span className="w-2.5 h-2.5 rounded-sm" style={{ background: "rgba(255,255,255,0.12)" }} /> Belum bermula</span>
      </div>
    </div>
  );
}
