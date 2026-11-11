"use client";

import { useEffect, useState } from "react";
import { Sparkles } from "lucide-react";
import api from "@/lib/api";
import { flagEmoji } from "@/lib/countries";
import { usdt } from "@/lib/format";

interface Activity {
  type: "deposit" | "rank";
  username: string;
  country: string | null;
  amount?: number;
  rank?: string;
  at: string;
}

function ago(iso: string): string {
  const s = Math.max(0, Math.floor((Date.now() - new Date(iso).getTime()) / 1000));
  if (s < 60) return "just now";
  const m = Math.floor(s / 60); if (m < 60) return `${m}m ago`;
  const h = Math.floor(m / 60); if (h < 24) return `${h}h ago`;
  const d = Math.floor(h / 24); return `${d}d ago`;
}

/**
 * Combined community activity feed — member deposits and earned rank promotions
 * from the last 7 days, newest first, with each member's country flag. Mirrors
 * WelcomeFeed; refreshes every 30s so it feels live. Shown below WelcomeFeed.
 */
export default function CommunityFeed() {
  const [items, setItems] = useState<Activity[]>([]);

  useEffect(() => {
    const load = () => api.get("/recent-activity").then((r) => setItems(r.data)).catch(() => {});
    load();
    const id = setInterval(load, 30000); // refresh every 30s so it feels live
    return () => clearInterval(id);
  }, []);

  if (items.length === 0) return null;

  return (
    <div className="glass p-6">
      <h3 className="font-semibold flex items-center gap-2">
        <Sparkles size={18} className="text-gold-light" /> Live Community Activity
      </h3>
      <p className="text-xs text-muted mt-1">Member deposits &amp; rank achievements — last 7 days 🎉</p>

      <div className="mt-4 space-y-2 max-h-96 overflow-y-auto">
        {items.map((a, i) => (
          <div
            key={`${a.username}-${a.at}-${i}`}
            className={`flex items-center gap-3 bg-black/30 rounded-lg px-3 py-2.5 border-l-[3px] ${a.type === "deposit" ? "border-emerald-400/70" : "border-gold"}`}
          >
            <span className="text-2xl shrink-0">{flagEmoji(a.country) || "🌐"}</span>
            <div className="flex-1 min-w-0">
              {a.type === "deposit" ? (
                <p className="text-sm truncate">
                  <b className="gold-text">{a.username}</b> deposited{" "}
                  <b className="text-emerald-400">{usdt(a.amount ?? 0)}</b>
                </p>
              ) : (
                <p className="text-sm truncate">
                  <b className="gold-text">{a.username}</b> reached{" "}
                  <span className="text-[11px] font-semibold uppercase px-1.5 py-0.5 rounded border border-[var(--line)] text-gold-light bg-gold-light/10">
                    {a.rank}
                  </span>
                </p>
              )}
              <p className="text-[11px] text-muted">{ago(a.at)}</p>
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}
