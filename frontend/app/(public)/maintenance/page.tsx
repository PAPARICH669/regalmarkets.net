"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import { Wrench } from "lucide-react";
import api from "@/lib/api";

interface Status {
  active: boolean;
  ends_at: string | null;
  next_start_at: string | null;
  window_start: string;
  window_end: string;
}

function fmt(s: number) {
  const h = Math.floor(s / 3600).toString().padStart(2, "0");
  const m = Math.floor((s % 3600) / 60).toString().padStart(2, "0");
  const sec = Math.floor(s % 60).toString().padStart(2, "0");
  return `${h}:${m}:${sec}`;
}

export default function MaintenancePage() {
  const [status, setStatus] = useState<Status | null>(null);
  const [remaining, setRemaining] = useState(0);

  useEffect(() => {
    api.get("/maintenance-status").then(({ data }) => {
      setStatus(data);
      const target = data.active ? data.ends_at : data.next_start_at;
      if (target) setRemaining(Math.max(0, Math.floor((new Date(target).getTime() - Date.now()) / 1000)));
    });
  }, []);

  useEffect(() => {
    const t = setInterval(() => setRemaining((r) => Math.max(0, r - 1)), 1000);
    return () => clearInterval(t);
  }, []);

  return (
    <div className="max-w-lg mx-auto px-5 py-24 text-center">
      <div className="glass p-10 animate-fade-up">
        <span className="inline-grid place-items-center w-16 h-16 rounded-2xl gold-gradient text-black mb-5 animate-floaty">
          <Wrench size={28} />
        </span>
        <h1 className="text-2xl font-bold">
          {status?.active ? "System Maintenance" : "System Live"}
        </h1>
        <p className="text-muted mt-2 text-sm">
          Daily maintenance runs {status?.window_start ?? "00:00"}–06:59 (Asia/Kuala_Lumpur).
          Login, withdrawals and transfers are paused and resume automatically at {status?.window_end ?? "07:00"}.
        </p>

        {status?.active && (
          <>
            <p className="text-muted mt-8 text-sm uppercase tracking-wide">Back online in</p>
            <p className="text-5xl font-bold gold-text font-mono mt-2">{fmt(remaining)}</p>
          </>
        )}

        <Link href="/login" className="btn-gold px-6 py-2.5 mt-8 inline-block">
          {status?.active ? "Try again later" : "Go to login"}
        </Link>
      </div>
    </div>
  );
}
