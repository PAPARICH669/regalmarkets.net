"use client";

import { useEffect, useRef } from "react";
import { useRouter } from "next/navigation";
import { useAuth } from "@/lib/auth";

const TIMEOUT_MS = 10 * 60 * 1000; // auto-logout after 10 minutes idle

/**
 * Logs the user out after 10 minutes with no activity (mouse, keyboard, touch,
 * scroll). Mount once inside an authenticated layout.
 */
export default function IdleLogout() {
  const router = useRouter();
  const logout = useAuth((s) => s.logout);
  const lastActivity = useRef<number>(Date.now());
  const firedRef = useRef(false);

  useEffect(() => {
    const bump = () => { lastActivity.current = Date.now(); };
    const events = ["mousemove", "mousedown", "keydown", "scroll", "touchstart", "click"];
    events.forEach((e) => window.addEventListener(e, bump, { passive: true }));

    const interval = setInterval(() => {
      if (firedRef.current) return;
      if (Date.now() - lastActivity.current >= TIMEOUT_MS) {
        firedRef.current = true;
        logout().finally(() => router.replace("/login"));
      }
    }, 15000); // check every 15s

    return () => {
      events.forEach((e) => window.removeEventListener(e, bump));
      clearInterval(interval);
    };
  }, [logout, router]);

  return null;
}
