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
 * Monthly profit bar chart on the member dashboard. Each bar = that month's
 * total passive profit (%), starting Jun 2026. Data is live from /monthly-profit
 * (daily ROI rate × distinct ROI days that month); the current month grows daily.
 * Lightweight — pure CSS/flex, no chart library.
 */
export default function MonthlyProfitChart() {
  const [months, setMonths] = useState<M[]>([]);
  const [total, setTotal] = useState(0);
  const [since, setSince] = useState("Jun 2026");

  useEffect(() => {
    api.get("/monthly-profit").then((r) => {
      setMonths(r.data.months || []);
      setTotal(r.data.total ?? 0);
      setSince(r.data.since ?? "Jun 2026");
    }).catch(() => {});
  }, []);

  if (months.length === 0) return null;

  const max = Math.max(...months.map((m) => m.value), 1);

  return (
    <div className="glass p-5">
      <div className="flex items-center justify-between flex-wrap gap-2">
        <div>
          <h3 className="font-semibold text-foreground">Monthly Profit — Regal Markets</h3>
          <p className="text-xs text-muted mt-0.5">Each bar = that month&apos;s total profit (%) · since Jun 2026</p>
        </div>
        <div className="text-right">
          <p className="text-[11px] text-muted whitespace-nowrap">Total profit since {since}</p>
          <p className="text-xl font-bold gold-text leading-tight">{total.toFixed(1)}%</p>
        </div>
      </div>

      <div className="mt-5 flex items-end gap-2 sm:gap-3" style={{ height: 180 }}>
        {months.map((m) => {
          const h = m.value > 0 ? Math.max((m.value / max) * 135, 5) : m.future ? 6 : 0;
          const bg = m.future
            ? "rgba(255,255,255,0.06)"
            : m.current
            ? "linear-gradient(180deg,#f2d98a,#c9a227)"
            : "linear-gradient(180deg,#e7c873,#c9a227)";
          return (
            <div key={m.label} className="flex-1 min-w-0 flex flex-col items-center justify-end h-full gap-1.5">
              <span className="text-[10px] text-gold-light whitespace-nowrap">{m.value > 0 ? `${m.value.toFixed(1)}%` : ""}</span>
              <div
                className="w-full rounded-t-md transition-all"
                style={{ height: `${h}px`, background: bg }}
                title={m.future ? `${m.label}: belum bermula` : `${m.label}: ${m.value.toFixed(1)}%${m.current ? " (current)" : ""}`}
              />
              <span className={`text-[10px] whitespace-nowrap ${m.future ? "text-muted/50" : "text-muted"}`}>{m.label}</span>
            </div>
          );
        })}
      </div>

      <div className="mt-3 flex items-center gap-4 flex-wrap text-[10px] text-muted">
        <span className="flex items-center gap-1.5"><span className="w-2.5 h-2.5 rounded-sm" style={{ background: "#e7c873" }} /> Selesai</span>
        <span className="flex items-center gap-1.5"><span className="w-2.5 h-2.5 rounded-sm" style={{ background: "#f2d98a" }} /> Bulan semasa</span>
        <span className="flex items-center gap-1.5"><span className="w-2.5 h-2.5 rounded-sm" style={{ background: "rgba(255,255,255,0.12)" }} /> Belum bermula</span>
      </div>
    </div>
  );
}
