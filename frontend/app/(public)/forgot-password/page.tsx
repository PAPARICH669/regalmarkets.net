"use client";

import { useState } from "react";
import Link from "next/link";
import api, { apiError } from "@/lib/api";

export default function ForgotPasswordPage() {
  const [email, setEmail] = useState("");
  const [msg, setMsg] = useState("");
  const [error, setError] = useState("");
  const [loading, setLoading] = useState(false);

  async function submit(e: React.FormEvent) {
    e.preventDefault();
    setError(""); setMsg(""); setLoading(true);
    try {
      const { data } = await api.post("/forgot-password", { email });
      setMsg(data.message || "If the email exists, a reset link has been sent.");
    } catch (err) {
      setError(apiError(err));
    } finally {
      setLoading(false);
    }
  }

  return (
    <div className="max-w-md mx-auto px-5 py-20">
      <div className="glass p-8 animate-fade-up">
        <h1 className="text-2xl font-bold">Forgot password?</h1>
        <p className="text-muted text-sm mt-1">
          Submit your account email and our admin will verify and reset your password,
          then contact you with a new one.
        </p>

        {msg && <div className="mt-4 text-sm text-green-400 bg-green-500/10 border border-green-500/30 rounded-lg px-4 py-3">{msg}</div>}
        {error && <div className="mt-4 text-sm text-red-400 bg-red-500/10 border border-red-500/30 rounded-lg px-4 py-3">{error}</div>}

        <form onSubmit={submit} className="mt-6 space-y-4">
          <input type="email" className="input-field" placeholder="you@email.com" value={email}
            onChange={(e) => setEmail(e.target.value)} required />
          <button disabled={loading} className="btn-gold w-full py-2.5">{loading ? "Submitting…" : "Request password reset"}</button>
        </form>

        <div className="mt-5 rounded-lg border border-[var(--line)] bg-black/20 p-3 text-xs text-muted">
          Need help fast? Contact support: <span className="text-gold-light">support@regalmarkets.net</span>
        </div>

        <p className="mt-5 text-sm text-muted text-center">
          <Link href="/" className="text-gold-light">Back to login</Link>
        </p>
      </div>
    </div>
  );
}
