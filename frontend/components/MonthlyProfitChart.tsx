"use client";

import { useEffect, useState } from "react";
import api from "@/lib/api";

interface M {
  label: string;
  value: number;
  current: boolean;
}

/**
 * Monthly profit bar chart on the member dashboard. Each bar = that month's
 * total passive profit (%), starting Jun 2026. Data is live from /monthly-profit
 * (daily ROI rate × distinct ROI days that month); the current month grows daily.
 * Lightweight — pure CSS/flex, no chart library.
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
      <div className="flex items-center justify-between flex-wrap gap-2">
        <div>
          <h3 className="font-semibold text-foreground">Monthly Profit — Regal Markets</h3>
          <p className="text-xs text-muted mt-0.5">Each bar = that month&apos;s total profit (%) · since Jun 2026</p>
        </div>
        <span className="text-xs text-green-400 flex items-center gap-1 whitespace-nowrap">▲ Passive earnings</span>
      </div>

      <div className="mt-5 flex items-end gap-2 sm:gap-3 overflow-x-auto" style={{ height: 180 }}>
        {months.map((m) => {
          const h = m.value > 0 ? Math.max((m.value / max) * 135, 5) : 0;
          return (
            <div key={m.label} className="flex-1 min-w-[34px] flex flex-col items-center justify-end h-full gap-1.5">
              <span className="text-[10px] text-gold-light whitespace-nowrap">{m.value > 0 ? `${m.value.toFixed(1)}%` : ""}</span>
              <div
                className="w-full rounded-t-md transition-all"
                style={{
                  height: `${h}px`,
                  background: m.current
                    ? "linear-gradient(180deg,#f2d98a,#c9a227)"
                    : "linear-gradient(180deg,#e7c873,#c9a227)",
                }}
                title={`${m.label}: ${m.value.toFixed(1)}%${m.current ? " (current)" : ""}`}
              />
              <span className="text-[10px] text-muted whitespace-nowrap">{m.label}</span>
            </div>
          );
        })}
      </div>
    </div>
  );
}
