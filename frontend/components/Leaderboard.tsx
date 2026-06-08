"use client";

import { useEffect, useState } from "react";
import { Trophy } from "lucide-react";
import api from "@/lib/api";
import { usdt } from "@/lib/format";

interface Row { position: number; name: string; rank: string; sales: number; }
interface Data { top: Row[]; }

const MEDAL: Record<number, string> = { 1: "🥇", 2: "🥈", 3: "🥉" };

export default function Leaderboard() {
  const [data, setData] = useState<Data | null>(null);

  useEffect(() => { api.get("/leaderboard").then((r) => setData(r.data)).catch(() => {}); }, []);

  return (
    <div className="glass p-6">
      <div className="flex items-center justify-between">
        <h3 className="font-semibold flex items-center gap-2">
          <Trophy size={18} className="text-gold-light" /> Top 5 Sponsor
        </h3>
      </div>
      <p className="text-xs text-muted mt-1">By direct sponsor&apos;s total invest · updated live. Climb the board! 🚀</p>

      <div className="mt-4 space-y-2">
        {data?.top?.map((r) => (
          <div key={r.position} className="flex items-center gap-3 bg-black/30 rounded-lg px-3 py-2.5">
            <span className="w-7 text-center text-lg shrink-0">{MEDAL[r.position] ?? <span className="text-sm text-muted">#{r.position}</span>}</span>
            <div className="flex-1 min-w-0">
              <p className="font-semibold truncate">{r.name}</p>
              <p className="text-[11px] text-gold-light uppercase tracking-wide">{r.rank}</p>
            </div>
            <span className="gold-text font-bold shrink-0">{usdt(r.sales)}</span>
          </div>
        ))}
        {data && data.top.length === 0 && (
          <p className="text-sm text-muted text-center py-4">No sales yet this month — be the first! 🏁</p>
        )}
        {!data && <p className="text-sm text-muted text-center py-4">Loading…</p>}
      </div>
    </div>
  );
}
