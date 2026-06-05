"use client";

import { useEffect, useState } from "react";
import { PartyPopper } from "lucide-react";
import api from "@/lib/api";
import { flagEmoji } from "@/lib/countries";

interface NewMember { username: string; country: string | null; created_at: string | null; }

function ago(iso: string | null): string {
  if (!iso) return "";
  const s = Math.max(0, Math.floor((Date.now() - new Date(iso).getTime()) / 1000));
  if (s < 60) return "just now";
  const m = Math.floor(s / 60); if (m < 60) return `${m}m ago`;
  const h = Math.floor(m / 60); if (h < 24) return `${h}h ago`;
  const d = Math.floor(h / 24); return `${d}d ago`;
}

export default function WelcomeFeed() {
  const [items, setItems] = useState<NewMember[]>([]);

  useEffect(() => {
    const load = () => api.get("/recent-members").then((r) => setItems(r.data)).catch(() => {});
    load();
    const id = setInterval(load, 30000); // refresh every 30s so it feels live
    return () => clearInterval(id);
  }, []);

  return (
    <div className="glass p-6">
      <h3 className="font-semibold flex items-center gap-2">
        <PartyPopper size={18} className="text-gold-light" /> Welcome New Members
      </h3>
      <p className="text-xs text-muted mt-1">Fresh registrations joining Regal Markets — live! 🎉</p>

      <div className="mt-4 space-y-2 max-h-72 overflow-y-auto">
        {items.map((m, i) => (
          <div key={`${m.username}-${i}`} className="flex items-center gap-3 bg-black/30 rounded-lg px-3 py-2.5">
            <span className="text-2xl shrink-0">{flagEmoji(m.country)}</span>
            <div className="flex-1 min-w-0">
              <p className="text-sm truncate">Welcome, <b className="gold-text">{m.username}</b>! 👋</p>
              <p className="text-[11px] text-muted">{ago(m.created_at)}</p>
            </div>
          </div>
        ))}
        {items.length === 0 && (
          <p className="text-sm text-muted text-center py-4">No new members yet — invite your team! 🚀</p>
        )}
      </div>
    </div>
  );
}
