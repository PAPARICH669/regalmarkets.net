"use client";

import { Suspense, useEffect, useState } from "react";
import { useRouter, useSearchParams } from "next/navigation";
import Link from "next/link";
import api, { apiError } from "@/lib/api";
import { useAuth } from "@/lib/auth";
import { COUNTRIES } from "@/lib/countries";
import Logo from "@/components/Logo";

function RegisterForm() {
  const router = useRouter();
  const params = useSearchParams();
  const setAuth = useAuth((s) => s.setAuth);

  const [form, setForm] = useState({
    username: "", email: "", phone: "", password: "", password_confirmation: "",
    referral_code: "",
  });
  const [country, setCountry] = useState(COUNTRIES[0].iso); // default Malaysia (MY)
  const dial = COUNTRIES.find((c) => c.iso === country)?.dial ?? COUNTRIES[0].dial;
  const [error, setError] = useState("");
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    const ref = params.get("ref");
    if (ref) setForm((f) => ({ ...f, referral_code: ref }));
  }, [params]);

  async function submit(e: React.FormEvent) {
    e.preventDefault();
    setError("");
    setLoading(true);
    try {
      // Combine the selected country dial code with the local number.
      const payload = { ...form, country, phone: `${dial}${form.phone.replace(/^0+/, "")}` };
      await api.post("/register", payload);
      // Account created; verify email with the 6-digit code.
      router.push(`/verify-email?email=${encodeURIComponent(form.email)}`);
    } catch (err) {
      setError(apiError(err, "Registration failed."));
    } finally {
      setLoading(false);
    }
  }

  return (
    <div className="max-w-md mx-auto px-5 py-12">
      <div className="flex justify-center mb-6"><Logo size="lg" /></div>
      <div className="glass p-8 animate-fade-up">
        <h1 className="text-2xl font-bold">Create your account</h1>
        <p className="text-muted text-sm mt-1">Join Regal Markets and activate your first package.</p>

        {error && <div className="mt-4 text-sm text-red-400 bg-red-500/10 border border-red-500/30 rounded-lg px-4 py-3">{error}</div>}

        <form onSubmit={submit} className="mt-6 space-y-4">
          <div>
            <label className="text-sm text-muted">Username</label>
            <input className="input-field mt-1" value={form.username}
              onChange={(e) => setForm({ ...form, username: e.target.value })} required />
          </div>
          <div>
            <label className="text-sm text-muted">Email</label>
            <input type="email" className="input-field mt-1" value={form.email}
              onChange={(e) => setForm({ ...form, email: e.target.value })} required />
          </div>
          <div>
            <label className="text-sm text-muted">Phone number <span className="text-red-500">*</span></label>
            <div className="mt-1 flex gap-2">
              <select
                className="input-field w-32 shrink-0"
                value={country}
                onChange={(e) => setCountry(e.target.value)}
                aria-label="Country"
              >
                {COUNTRIES.map((c) => (
                  <option key={c.iso} value={c.iso}>{c.flag} {c.dial}</option>
                ))}
              </select>
              <input type="tel" className="input-field flex-1" value={form.phone}
                onChange={(e) => setForm({ ...form, phone: e.target.value })} placeholder="12 345 6789" required />
            </div>
          </div>
          <div className="grid grid-cols-2 gap-3">
            <div>
              <label className="text-sm text-muted">Password</label>
              <input type="password" className="input-field mt-1" value={form.password}
                onChange={(e) => setForm({ ...form, password: e.target.value })} required />
            </div>
            <div>
              <label className="text-sm text-muted">Confirm</label>
              <input type="password" className="input-field mt-1" value={form.password_confirmation}
                onChange={(e) => setForm({ ...form, password_confirmation: e.target.value })} required />
            </div>
          </div>
          {form.referral_code && (
            <p className="text-xs text-gold-light">Sponsor: <b>{form.referral_code}</b> ✓</p>
          )}
          <button disabled={loading} className="btn-gold w-full py-2.5">
            {loading ? "Creating…" : "Create Account"}
          </button>
        </form>

        <p className="mt-5 text-sm text-muted text-center">
          Already have an account? <Link href="/login" className="text-gold-light">Login</Link>
        </p>
      </div>
    </div>
  );
}

export default function RegisterPage() {
  return (
    <Suspense fallback={<div className="py-20 text-center text-muted">Loading…</div>}>
      <RegisterForm />
    </Suspense>
  );
}
