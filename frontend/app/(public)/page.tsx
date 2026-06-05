"use client";

import { useEffect, useState } from "react";
import { useRouter } from "next/navigation";
import Link from "next/link";
import { Wrench } from "lucide-react";
import api, { apiError } from "@/lib/api";
import { useAuth } from "@/lib/auth";
import Logo from "@/components/Logo";

interface Maint { active: boolean; window_start: string; window_end: string; manual?: boolean; ends_at?: string | null; }

// "07:01" -> "07:00" (the displayed maintenance period ends one minute before login re-opens)
function minusOneMinute(hhmm: string): string {
  const [h, m] = (hhmm || "00:00").split(":").map(Number);
  const t = (h * 60 + m + 24 * 60 - 1) % (24 * 60);
  return `${String(Math.floor(t / 60)).padStart(2, "0")}:${String(t % 60).padStart(2, "0")}`;
}

function pad(n: number) { return String(n).padStart(2, "0"); }

function MaintenanceNotice({ m }: { m: Maint }) {
  const [left, setLeft] = useState<number>(() =>
    m.ends_at ? Math.max(0, Math.floor((new Date(m.ends_at).getTime() - Date.now()) / 1000)) : 0
  );

  useEffect(() => {
    if (!m.ends_at) return;
    const tick = () => {
      const s = Math.max(0, Math.floor((new Date(m.ends_at as string).getTime() - Date.now()) / 1000));
      setLeft(s);
      if (s <= 0) window.location.reload(); // window ended → refresh so login opens
    };
    tick();
    const id = setInterval(tick, 1000);
    return () => clearInterval(id);
  }, [m.ends_at]);

  const h = Math.floor(left / 3600);
  const mins = Math.floor((left % 3600) / 60);
  const secs = left % 60;

  return (
    <div className="mb-5 rounded-xl border border-gold-light/30 bg-gold-light/5 p-4 text-center">
      <div className="flex items-center justify-center gap-2 text-gold-light font-semibold">
        <Wrench size={18} /> System Maintenance
      </div>
      <p className="mt-2 text-sm text-muted">
        Login is disabled during daily maintenance{" "}
        <b className="text-foreground">{m.window_start} – {minusOneMinute(m.window_end)}</b>.
      </p>
      {m.ends_at && (
        <>
          <p className="mt-3 text-xs text-muted uppercase tracking-wide">Login opens in</p>
          <p className="mt-1 text-2xl font-bold gold-text tabular-nums">
            {pad(h)}:{pad(mins)}:{pad(secs)}
          </p>
        </>
      )}
      <p className="mt-2 text-sm text-muted">
        You can log in again at <b className="text-gold-light">{m.window_end}</b>.
      </p>
    </div>
  );
}

export default function LandingLoginPage() {
  const router = useRouter();
  const setAuth = useAuth((s) => s.setAuth);
  const [form, setForm] = useState({ email: "", password: "" });
  const [error, setError] = useState("");
  const [loading, setLoading] = useState(false);
  const [maint, setMaint] = useState<Maint | null>(null);

  // Show the maintenance banner immediately if the system is in the window.
  useEffect(() => {
    api.get("/maintenance-status")
      .then((r) => { if (r.data?.active) setMaint(r.data); })
      .catch(() => {});
  }, []);

  async function submit(e: React.FormEvent) {
    e.preventDefault();
    setError("");
    setLoading(true);
    try {
      const { data } = await api.post("/login", form);
      setAuth(data.token, data.user);
      router.push(data.user.is_admin ? "/admin" : "/dashboard");
    } catch (err) {
      const e = err as { response?: { status?: number; data?: { needs_verification?: boolean; email?: string; maintenance?: Maint } } };
      if (e.response?.data?.needs_verification) {
        router.push(`/verify-email?email=${encodeURIComponent(e.response.data.email || form.email)}`);
        return;
      }
      // Maintenance block (members only) — show the notice instead of a plain error.
      if (e.response?.status === 503 && e.response.data?.maintenance) {
        setMaint(e.response.data.maintenance);
        setError("");
        return;
      }
      setError(apiError(err, "Login failed."));
    } finally {
      setLoading(false);
    }
  }

  return (
    <div className="w-full max-w-md mx-auto px-5">
      <div className="flex justify-center mb-8">
        <Logo size="xl" href="/" />
      </div>

      <div className="glass p-8 animate-fade-up">
        {maint && <MaintenanceNotice m={maint} />}

        {error && (
          <div className="mb-4 text-sm text-red-400 bg-red-500/10 border border-red-500/30 rounded-lg px-4 py-3">
            {error}
          </div>
        )}

        <form onSubmit={submit} className="space-y-4">
          <div>
            <label className="text-sm text-muted">Email</label>
            <input
              type="email"
              className="input-field mt-1"
              value={form.email}
              onChange={(e) => setForm({ ...form, email: e.target.value })}
              placeholder="you@email.com"
              required
            />
          </div>
          <div>
            <label className="text-sm text-muted">Password</label>
            <input
              type="password"
              className="input-field mt-1"
              value={form.password}
              onChange={(e) => setForm({ ...form, password: e.target.value })}
              placeholder="••••••••"
              required
            />
          </div>
          <button disabled={loading} className="btn-gold w-full py-2.5">
            {loading ? "Signing in…" : "Login"}
          </button>
        </form>

        <div className="mt-5 text-center">
          <Link href="/forgot-password" className="text-sm text-muted hover:text-gold-light">
            Forgot password?
          </Link>
        </div>
      </div>
    </div>
  );
}
