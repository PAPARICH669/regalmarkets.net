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
 * Monthly profit bar chart on the member dashboard. The FIRST bar (Jun 2026 —
 * when Regal Markets began paying) shows the grand TOTAL passive profit %
 * accumulated since inception; it grows daily as ROI is distributed. The other
 * months render as empty placeholders. Platform-wide data from /monthly-profit
 * (total field) — identical for every member. Lightweight, no chart library.
 */
export default function MonthlyProfitChart() {
  const [months, setMonths] = useState<M[]>([]);
  const [total, setTotal] = useState(0);

  useEffect(() => {
    api.get("/monthly-profit").then((r) => {
      setMonths(r.data.months || []);
      setTotal(r.data.total ?? 0);
    }).catch(() => {});
  }, []);

  if (months.length === 0) return null;

  const max = Math.max(total, 1);

  return (
    <div className="glass p-5">
      <div>
        <h3 className="font-semibold text-foreground">Monthly Profit — Regal Markets</h3>
        <p className="text-xs text-muted mt-0.5">Total passive profit accumulated · since Jun 2026</p>
      </div>

      <div className="mt-5 flex items-end gap-2 sm:gap-3" style={{ height: 180 }}>
        {months.map((m, i) => {
          const isTotalBar = i === 0; // Jun holds the grand total since inception
          const showVal = isTotalBar && total > 0;
          const h = showVal ? Math.max((total / max) * 135, 5) : 6;
          const bg = isTotalBar
            ? "linear-gradient(180deg,#f2d98a,#c9a227)"
            : "rgba(255,255,255,0.06)";
          return (
            <div key={m.label} className="flex-1 min-w-0 flex flex-col items-center justify-end h-full gap-1.5">
              <span className="text-[11px] font-semibold text-gold-light whitespace-nowrap">{showVal ? `${total.toFixed(1)}%` : ""}</span>
              <div
                className="w-full rounded-t-md transition-all"
                style={{ height: `${h}px`, background: bg }}
                title={isTotalBar ? `Total profit since ${m.label}: ${total.toFixed(1)}%` : `${m.label}: belum bermula`}
              />
              <span className={`text-[10px] whitespace-nowrap ${isTotalBar ? "text-muted" : "text-muted/50"}`}>{m.label}</span>
            </div>
          );
        })}
      </div>

      <div className="mt-3 flex items-center gap-4 flex-wrap text-[10px] text-muted">
        <span className="flex items-center gap-1.5"><span className="w-2.5 h-2.5 rounded-sm" style={{ background: "#f2d98a" }} /> Jumlah terkumpul (sejak Jun 2026)</span>
        <span className="flex items-center gap-1.5"><span className="w-2.5 h-2.5 rounded-sm" style={{ background: "rgba(255,255,255,0.12)" }} /> Belum bermula</span>
      </div>
    </div>
  );
}
