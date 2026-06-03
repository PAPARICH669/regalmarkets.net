"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";
import Link from "next/link";
import api, { apiError } from "@/lib/api";
import { useAuth } from "@/lib/auth";
import Logo from "@/components/Logo";

export default function LandingLoginPage() {
  const router = useRouter();
  const setAuth = useAuth((s) => s.setAuth);
  const [form, setForm] = useState({ login: "", password: "" });
  const [error, setError] = useState("");
  const [loading, setLoading] = useState(false);

  async function submit(e: React.FormEvent) {
    e.preventDefault();
    setError("");
    setLoading(true);
    try {
      const { data } = await api.post("/login", form);
      setAuth(data.token, data.user);
      router.push(data.user.is_admin ? "/admin" : "/dashboard");
    } catch (err) {
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
        {error && (
          <div className="mb-4 text-sm text-red-400 bg-red-500/10 border border-red-500/30 rounded-lg px-4 py-3">
            {error}
          </div>
        )}

        <form onSubmit={submit} className="space-y-4">
          <div>
            <label className="text-sm text-muted">Login</label>
            <input
              className="input-field mt-1"
              value={form.login}
              onChange={(e) => setForm({ ...form, login: e.target.value })}
              placeholder="Username or email"
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
