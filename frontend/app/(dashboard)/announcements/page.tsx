"use client";

import { useEffect, useState } from "react";
import { Megaphone } from "lucide-react";
import api from "@/lib/api";
import { shortDate } from "@/lib/format";

interface Announcement { id: number; title: string; body: string; published_at: string; }

export default function AnnouncementsPage() {
  const [items, setItems] = useState<Announcement[]>([]);
  useEffect(() => { api.get("/announcements").then((r) => setItems(r.data)); }, []);

  return (
    <div className="space-y-6">
      <h1 className="text-2xl font-bold">Announcements</h1>
      <div className="space-y-4">
        {items.map((a) => (
          <div key={a.id} className="glass p-5">
            <div className="flex items-center gap-2">
              <Megaphone size={18} className="text-gold-light" />
              <h3 className="font-semibold">{a.title}</h3>
              <span className="ml-auto text-xs text-muted">{shortDate(a.published_at)}</span>
            </div>
            <p className="mt-2 text-sm text-muted whitespace-pre-line">{a.body}</p>
          </div>
        ))}
        {items.length === 0 && <p className="text-muted">No announcements right now.</p>}
      </div>
    </div>
  );
}
