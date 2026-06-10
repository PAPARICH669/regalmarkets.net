"use client";

import { useEffect, useState } from "react";
import { KeyRound, Wallet, UserCog, ShieldCheck, Users } from "lucide-react";
import api, { apiError } from "@/lib/api";
import { useAuth } from "@/lib/auth";

const KYC_BADGE: Record<string, string> = {
  unsubmitted: "bg-white/10 text-muted",
  pending: "bg-yellow-500/15 text-yellow-400",
  verified: "bg-green-500/15 text-green-400",
  rejected: "bg-red-500/15 text-red-400",
};

export default function ProfilePage() {
  const { user, refresh } = useAuth();

  // ----- Profile (nickname, beneficiary, wallet) -----
  const [form, setForm] = useState({ nickname: "", heir_name: "", heir_phone: "" });
  const [pMsg, setPMsg] = useState(""); const [pErr, setPErr] = useState(""); const [pLoad, setPLoad] = useState(false);

  // ----- Password -----
  const [pw, setPw] = useState({ current_password: "", password: "", password_confirmation: "" });
  const [pwMsg, setPwMsg] = useState(""); const [pwErr, setPwErr] = useState(""); const [pwLoad, setPwLoad] = useState(false);

  // ----- KYC -----
  const [kyc, setKyc] = useState<{ id_type: string; id_number: string; document: File | null }>({ id_type: "ic", id_number: "", document: null });
  const [kMsg, setKMsg] = useState(""); const [kErr, setKErr] = useState(""); const [kLoad, setKLoad] = useState(false);

  useEffect(() => {
    if (user) setForm({
      nickname: user.nickname || user.username || "",
      heir_name: user.heir_name || "",
      heir_phone: user.heir_phone || "",
    });
  }, [user]);

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

  async function submitKyc(e: React.FormEvent) {
    e.preventDefault(); setKMsg(""); setKErr(""); setKLoad(true);
    try {
      const fd = new FormData();
      fd.append("id_type", kyc.id_type); fd.append("id_number", kyc.id_number);
      if (kyc.document) fd.append("document", kyc.document);
      const { data } = await api.post("/kyc", fd, { headers: { "Content-Type": "multipart/form-data" } });
      setKMsg(data.message); refresh();
    } catch (err) { setKErr(apiError(err)); } finally { setKLoad(false); }
  }

  const status = user?.kyc_status || "unsubmitted";

  return (
    <div className="space-y-6 max-w-2xl">
      <h1 className="text-2xl font-bold">Profile & Security</h1>

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
              <label className="text-sm text-muted">Email <span className="text-xs">(admin only)</span></label>
              <input className="input-field mt-1 opacity-60" value={user?.email || ""} disabled />
            </div>
            <div>
              <label className="text-sm text-muted">Phone number <span className="text-xs">(admin only)</span></label>
              <input className="input-field mt-1 opacity-60" value={user?.phone || "—"} disabled />
            </div>
          </div>
          <p className="text-xs text-muted">To change your email or phone, contact admin/support.</p>

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

          <div className="border-t border-[var(--line)] pt-4">
            <label className="text-sm text-muted flex items-center gap-2"><Wallet size={15} className="text-gold-light" /> USDT Withdrawal Address <span className="text-xs">(admin only)</span> <span className="text-gold-light text-xs">(BEP20)</span></label>
            <input className="input-field mt-1 opacity-60" value={user?.wallet_address || "—"} disabled />
            <p className="text-xs text-muted mt-1">🔒 For security, your withdrawal address can only be set or changed by admin. Contact admin/support to update it.</p>
          </div>

          <button disabled={pLoad} className="btn-gold px-6 py-2.5">{pLoad ? "Saving…" : "Save Profile"}</button>
        </form>
      </div>

      {/* KYC */}
      <div className="glass p-6">
        <div className="flex items-center justify-between">
          <h3 className="font-semibold flex items-center gap-2"><ShieldCheck size={18} className="text-gold-light" /> KYC Verification</h3>
          <span className={`text-xs px-3 py-1 rounded-full capitalize ${KYC_BADGE[status]}`}>{status}</span>
        </div>
        {status === "verified" ? (
          <p className="text-sm text-green-400 mt-3">✓ Your identity is verified.</p>
        ) : (
          <>
            <p className="text-sm text-muted mt-1">
              Upload a valid, non-expired <b className="text-foreground">National ID card, Passport, or Driving License</b>.
              KYC is processed within <b className="text-foreground">72 working hours</b> (Mon–Fri, weekends excluded).
              <b className="text-red-400"> You cannot withdraw until your KYC is verified.</b>
              {status === "rejected" && user?.kyc_note && <span className="text-red-400"> Rejected: {user.kyc_note}</span>}
              {status === "pending" && <span className="text-yellow-400"> Pending admin review…</span>}
            </p>
            {kMsg && <div className="mt-4 text-sm text-green-400 bg-green-500/10 border border-green-500/30 rounded-lg px-4 py-3">{kMsg}</div>}
            {kErr && <div className="mt-4 text-sm text-red-400 bg-red-500/10 border border-red-500/30 rounded-lg px-4 py-3">{kErr}</div>}
            <form onSubmit={submitKyc} className="mt-4 space-y-4">
              <div className="grid sm:grid-cols-2 gap-4">
                <div>
                  <label className="text-sm text-muted">ID type <span className="text-red-500">*</span></label>
                  <select className="input-field mt-1" value={kyc.id_type} onChange={(e) => setKyc({ ...kyc, id_type: e.target.value })}>
                    <option value="ic">National ID Card (IC)</option>
                    <option value="passport">Passport</option>
                    <option value="license">Driving License</option>
                  </select>
                </div>
                <div>
                  <label className="text-sm text-muted">ID number <span className="text-red-500">*</span></label>
                  <input className="input-field mt-1" value={kyc.id_number} onChange={(e) => setKyc({ ...kyc, id_number: e.target.value })} required />
                </div>
              </div>
              <div>
                <label className="text-sm text-muted">Upload document <span className="text-red-500">*</span> <span className="text-xs">(valid &amp; not expired · image or PDF, max 8MB)</span></label>
                <input type="file" accept="image/*,.pdf,.heic,.heif" className="input-field mt-1" onChange={(e) => setKyc({ ...kyc, document: e.target.files?.[0] || null })} required />
              </div>
              <button disabled={kLoad} className="btn-gold px-6 py-2.5">{kLoad ? "Submitting…" : "Submit KYC"}</button>
            </form>
          </>
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
