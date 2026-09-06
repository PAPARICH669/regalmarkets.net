"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import { KeyRound, Wallet, UserCog, ShieldCheck, Users } from "lucide-react";
import api, { apiError } from "@/lib/api";
import { useAuth } from "@/lib/auth";
import { ChangeRequestForm, CRStatus, CR_FIELD, type ChangeReq } from "@/components/ChangeRequestForm";

export default function ProfilePage() {
  const { user, refresh } = useAuth();

  // ----- Profile (nickname, beneficiary) -----
  const [form, setForm] = useState({ nickname: "", heir_name: "", heir_phone: "" });
  const [pMsg, setPMsg] = useState(""); const [pErr, setPErr] = useState(""); const [pLoad, setPLoad] = useState(false);

  // ----- Password -----
  const [pw, setPw] = useState({ current_password: "", password: "", password_confirmation: "" });
  const [pwMsg, setPwMsg] = useState(""); const [pwErr, setPwErr] = useState(""); const [pwLoad, setPwLoad] = useState(false);

  // ----- Email / phone change requests -----
  const [requests, setRequests] = useState<ChangeReq[]>([]);
  const loadRequests = () => api.get("/account-changes").then((r) => setRequests(r.data)).catch(() => {});

  useEffect(() => {
    if (user) {
      setForm({
        nickname: user.nickname || user.username || "",
        heir_name: user.heir_name || "",
        heir_phone: user.heir_phone || "",
      });
    }
  }, [user]);
  useEffect(() => { loadRequests(); }, []);

  const contactReqs = requests.filter((r) => r.field === "email" || r.field === "phone");

  async function saveProfile(e: React.FormEvent) {
    e.preventDefault(); setPMsg(""); setPErr(""); setPLoad(true);
    try { const { data } = await api.put("/profile", form); setPMsg(data.message); refresh(); }
    catch (err) { setPErr(apiError(err)); } finally { setPLoad(false); }
  }

  async function changePassword(e: React.FormEvent) {
    e.preventDefault(); setPwMsg(""); setPwErr(""); setPwLoad(true);
    try { const { data } = await api.put("/profile/password", pw); setPwMsg(data.message); setPw({ current_password: "", password: "", password_confirmation: "" }); }
    catch (err) { setPwErr(apiError(err)); } finally { setPwLoad(false); }
  }

  return (
    <div className="space-y-6 max-w-2xl">
      <h1 className="text-2xl font-bold">Profile & Security</h1>

      {/* Quick links to the separated sections */}
      <div className="grid sm:grid-cols-2 gap-4">
        <Link href="/wallet" className="glass p-4 flex items-center gap-3 hover:border-gold-light/40 transition">
          <span className="w-10 h-10 rounded-full bg-gold-light/15 grid place-items-center"><Wallet size={18} className="text-gold-light" /></span>
          <span><span className="block font-semibold">Wallet Addresses</span><span className="text-xs text-muted">Manage USDT / BTC / ETH / SOL payout addresses</span></span>
        </Link>
        <Link href="/kyc" className="glass p-4 flex items-center gap-3 hover:border-gold-light/40 transition">
          <span className="w-10 h-10 rounded-full bg-gold-light/15 grid place-items-center"><ShieldCheck size={18} className="text-gold-light" /></span>
          <span><span className="block font-semibold">KYC Verification</span><span className="text-xs text-muted">Submit / check your identity verification</span></span>
        </Link>
      </div>

      {/* Account details */}
      <div className="glass p-6">
        <h3 className="font-semibold flex items-center gap-2"><UserCog size={18} className="text-gold-light" /> Account Details</h3>
        {pMsg && <div className="mt-4 text-sm text-green-400 bg-green-500/10 border border-green-500/30 rounded-lg px-4 py-3">{pMsg}</div>}
        {pErr && <div className="mt-4 text-sm text-red-400 bg-red-500/10 border border-red-500/30 rounded-lg px-4 py-3">{pErr}</div>}
        <form onSubmit={saveProfile} className="mt-5 space-y-4">
          <div className="grid sm:grid-cols-2 gap-4">
            <div>
              <label className="text-sm text-muted">Nick Name <span className="text-gold-light text-xs">(editable)</span></label>
              <input className="input-field mt-1" value={form.nickname} onChange={(e) => setForm({ ...form, nickname: e.target.value })} maxLength={30} />
            </div>
            <div>
              <label className="text-sm text-muted">Email <span className="text-xs">(change below)</span></label>
              <input className="input-field mt-1 opacity-60" value={user?.email || ""} disabled />
            </div>
            <div>
              <label className="text-sm text-muted">Phone number <span className="text-xs">(change below)</span></label>
              <input className="input-field mt-1 opacity-60" value={user?.phone || "—"} disabled />
            </div>
          </div>

          <div className="border-t border-[var(--line)] pt-4">
            <p className="text-sm font-medium flex items-center gap-2 mb-3"><Users size={16} className="text-gold-light" /> Beneficiary (Pewaris)</p>
            <div className="grid sm:grid-cols-2 gap-4">
              <div>
                <label className="text-sm text-muted">Beneficiary name</label>
                <input className="input-field mt-1" value={form.heir_name} onChange={(e) => setForm({ ...form, heir_name: e.target.value })} />
              </div>
              <div>
                <label className="text-sm text-muted">Beneficiary phone</label>
                <input className="input-field mt-1" value={form.heir_phone} onChange={(e) => setForm({ ...form, heir_phone: e.target.value })} />
              </div>
            </div>
          </div>

          <button disabled={pLoad} className="btn-gold px-6 py-2.5">{pLoad ? "Saving…" : "Save Profile"}</button>
        </form>
      </div>

      {/* Email / phone change requests */}
      <div className="glass p-6">
        <h3 className="font-semibold flex items-center gap-2"><UserCog size={18} className="text-gold-light" /> Email &amp; Phone Change</h3>
        <p className="text-sm text-muted mt-1">Each change needs a 6-digit code (TAC) sent to your <b>current email</b>, then admin/staff approval before it takes effect.</p>

        <div className="mt-5 grid sm:grid-cols-2 gap-5">
          <ChangeRequestForm field="email" label="New Email" current={user?.email || "—"} placeholder="you@email.com" inputType="email" onDone={loadRequests} />
          <ChangeRequestForm field="phone" label="New Phone Number" current={user?.phone || "—"} placeholder="+60123456789" inputType="tel" onDone={loadRequests} />
        </div>

        {contactReqs.length > 0 && (
          <div className="mt-6 border-t border-[var(--line)] pt-4">
            <p className="text-sm font-medium mb-2">Your recent requests</p>
            <div className="space-y-2">
              {contactReqs.map((r) => (
                <div key={r.id} className="flex items-center justify-between gap-3 text-sm bg-black/30 rounded-lg px-3 py-2">
                  <span className="text-muted">{CR_FIELD[r.field] || r.field} → <span className="text-foreground break-all">{r.new_value}</span></span>
                  <CRStatus status={r.status} />
                </div>
              ))}
            </div>
          </div>
        )}
      </div>

      {/* Password */}
      <div className="glass p-6">
        <h3 className="font-semibold flex items-center gap-2"><KeyRound size={18} className="text-gold-light" /> Change Password</h3>
        {pwMsg && <div className="mt-4 text-sm text-green-400 bg-green-500/10 border border-green-500/30 rounded-lg px-4 py-3">{pwMsg}</div>}
        {pwErr && <div className="mt-4 text-sm text-red-400 bg-red-500/10 border border-red-500/30 rounded-lg px-4 py-3">{pwErr}</div>}
        <form onSubmit={changePassword} className="mt-5 space-y-4">
          <div>
            <label className="text-sm text-muted">Current password</label>
            <input type="password" className="input-field mt-1" value={pw.current_password} onChange={(e) => setPw({ ...pw, current_password: e.target.value })} required />
          </div>
          <div className="grid sm:grid-cols-2 gap-3">
            <div>
              <label className="text-sm text-muted">New password</label>
              <input type="password" className="input-field mt-1" value={pw.password} onChange={(e) => setPw({ ...pw, password: e.target.value })} required minLength={6} />
            </div>
            <div>
              <label className="text-sm text-muted">Confirm</label>
              <input type="password" className="input-field mt-1" value={pw.password_confirmation} onChange={(e) => setPw({ ...pw, password_confirmation: e.target.value })} required minLength={6} />
            </div>
          </div>
          <button disabled={pwLoad} className="btn-gold px-6 py-2.5">{pwLoad ? "Saving…" : "Change Password"}</button>
        </form>
      </div>
    </div>
  );
}
