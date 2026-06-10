"use client";

import { Suspense, useState } from "react";
import { useRouter, useSearchParams } from "next/navigation";
import Link from "next/link";
import { ShieldCheck } from "lucide-react";
import api, { apiError } from "@/lib/api";
import { useAuth } from "@/lib/auth";
import Logo from "@/components/Logo";

function VerifyLoginForm() {
  const router = useRouter();
  const params = useSearchParams();
  const setAuth = useAuth((s) => s.setAuth);
  const email = params.get("email") || "";

  const [code, setCode] = useState("");
  const [msg, setMsg] = useState(""); const [error, setError] = useState("");
  const [loading, setLoading] = useState(false); const [resending, setResending] = useState(false);

  async function submit(e: React.FormEvent) {
    e.preventDefault(); setError(""); setMsg(""); setLoading(true);
    try {
      const { data } = await api.post("/login/verify-tac", { email, code });
      setAuth(data.token, data.user);
      const dest = data.user.is_admin ? "/admin" : data.user.is_staff ? "/admin/kyc" : "/dashboard";
      router.push(dest);
    } catch (err) { setError(apiError(err, "Verification failed.")); } finally { setLoading(false); }
  }

  async function resend() {
    setError(""); setMsg(""); setResending(true);
    try { const { data } = await api.post("/login/resend-tac", { email }); setMsg(data.message); }
    catch (err) { setError(apiError(err)); } finally { setResending(false); }
  }

  return (
    <div className="w-full max-w-md mx-auto px-5">
      <div className="flex justify-center mb-6"><Logo size="lg" href="/" /></div>
      <div className="glass p-8 animate-fade-up">
        <div className="flex items-center gap-2"><ShieldCheck className="text-gold-light" /><h1 className="text-2xl font-bold">Login verification</h1></div>
        <p className="text-muted text-sm mt-1">For your security, we sent a 6-digit code (TAC) to <b className="text-foreground">{email || "your email"}</b>. Enter it to finish signing in.</p>

        {msg && <div className="mt-4 text-sm text-green-400 bg-green-500/10 border border-green-500/30 rounded-lg px-4 py-3">{msg}</div>}
        {error && <div className="mt-4 text-sm text-red-400 bg-red-500/10 border border-red-500/30 rounded-lg px-4 py-3">{error}</div>}

        <form onSubmit={submit} className="mt-6 space-y-4">
          <input inputMode="numeric" maxLength={6} className="input-field text-center text-2xl tracking-[0.5em] font-mono"
            placeholder="······" value={code} onChange={(e) => setCode(e.target.value.replace(/\D/g, ""))} required />
          <button disabled={loading || code.length < 6} className="btn-gold w-full py-2.5">{loading ? "Verifying…" : "Verify & Sign in"}</button>
        </form>

        <div className="mt-5 flex items-center justify-between text-sm">
          <button onClick={resend} disabled={resending} className="text-gold-light hover:underline disabled:opacity-50">
            {resending ? "Sending…" : "Resend code"}
          </button>
          <Link href="/" className="text-muted hover:text-gold-light">Back to login</Link>
        </div>
        <p className="mt-4 text-xs text-muted">The code expires in 10 minutes. Didn&apos;t get it? Check your Spam/Promotions folder.</p>
      </div>
    </div>
  );
}

export default function VerifyLoginPage() {
  return (
    <Suspense fallback={<div className="py-20 text-center text-muted">Loading…</div>}>
      <VerifyLoginForm />
    </Suspense>
  );
}
