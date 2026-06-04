"use client";

import { Suspense, useState } from "react";
import { useRouter, useSearchParams } from "next/navigation";
import Link from "next/link";
import api, { apiError } from "@/lib/api";
import Logo from "@/components/Logo";

function ResetForm() {
  const router = useRouter();
  const params = useSearchParams();
  const token = params.get("token") || "";
  const email = params.get("email") || "";

  const [password, setPassword] = useState("");
  const [confirm, setConfirm] = useState("");
  const [msg, setMsg] = useState(""); const [error, setError] = useState("");
  const [loading, setLoading] = useState(false);

  async function submit(e: React.FormEvent) {
    e.preventDefault(); setMsg(""); setError(""); setLoading(true);
    try {
      const { data } = await api.post("/reset-password", {
        token, email, password, password_confirmation: confirm,
      });
      setMsg(data.message || "Password reset. Redirecting to login…");
      setTimeout(() => router.push("/"), 1500);
    } catch (err) { setError(apiError(err)); } finally { setLoading(false); }
  }

  return (
    <div className="w-full max-w-md mx-auto px-5">
      <div className="flex justify-center mb-8"><Logo size="lg" href="/" /></div>
      <div className="glass p-8 animate-fade-up">
        <h1 className="text-2xl font-bold">Set a new password</h1>
        <p className="text-muted text-sm mt-1">{email ? `For ${email}` : "Use the link from your email."}</p>

        {!token && <div className="mt-4 text-sm text-yellow-400 bg-yellow-500/10 border border-yellow-500/30 rounded-lg px-4 py-3">Missing reset token. Open the link from your reset email.</div>}
        {msg && <div className="mt-4 text-sm text-green-400 bg-green-500/10 border border-green-500/30 rounded-lg px-4 py-3">{msg}</div>}
        {error && <div className="mt-4 text-sm text-red-400 bg-red-500/10 border border-red-500/30 rounded-lg px-4 py-3">{error}</div>}

        <form onSubmit={submit} className="mt-6 space-y-4">
          <div>
            <label className="text-sm text-muted">New password</label>
            <input type="password" className="input-field mt-1" value={password}
              onChange={(e) => setPassword(e.target.value)} required minLength={6} />
          </div>
          <div>
            <label className="text-sm text-muted">Confirm password</label>
            <input type="password" className="input-field mt-1" value={confirm}
              onChange={(e) => setConfirm(e.target.value)} required minLength={6} />
          </div>
          <button disabled={loading || !token} className="btn-gold w-full py-2.5">{loading ? "Resetting…" : "Reset Password"}</button>
        </form>

        <p className="mt-5 text-sm text-muted text-center">
          <Link href="/" className="text-gold-light">Back to login</Link>
        </p>
      </div>
    </div>
  );
}

export default function ResetPasswordPage() {
  return (
    <Suspense fallback={<div className="py-20 text-center text-muted">Loading…</div>}>
      <ResetForm />
    </Suspense>
  );
}
