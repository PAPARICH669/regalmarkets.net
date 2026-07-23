"use client";

import { useEffect, useState } from "react";
import { KeyRound, Wallet, UserCog, ShieldCheck, Users } from "lucide-react";
import api, { apiError } from "@/lib/api";
import { useAuth } from "@/lib/auth";
import { COUNTRIES, idFormatHint } from "@/lib/countries";

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
  const [kyc, setKyc] = useState<{ kyc_country: string; id_type: string; id_number: string; document: File | null; selfie: File | null }>(
    { kyc_country: "MY", id_type: "ic", id_number: "", document: null, selfie: null });
  const [kMsg, setKMsg] = useState(""); const [kErr, setKErr] = useState(""); const [kLoad, setKLoad] = useState(false);

  // ----- Email / wallet change requests -----
  const [requests, setRequests] = useState<ChangeReq[]>([]);
  const loadRequests = () => api.get("/account-changes").then((r) => setRequests(r.data)).catch(() => {});

  useEffect(() => {
    if (user) {
      setForm({
        nickname: user.nickname || user.username || "",
        heir_name: user.heir_name || "",
        heir_phone: user.heir_phone || "",
      });
      // Pre-select the member's registered country if it is one we accept.
      const iso = (user.country || "").toUpperCase();
      if (COUNTRIES.some((c) => c.iso === iso)) setKyc((k) => ({ ...k, kyc_country: iso }));
    }
  }, [user]);

  useEffect(() => { loadRequests(); }, []);

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
      fd.append("kyc_country", kyc.kyc_country);
      fd.append("id_type", kyc.id_type); fd.append("id_number", kyc.id_number);
      if (kyc.document) fd.append("document", kyc.document);
      if (kyc.selfie) fd.append("selfie", kyc.selfie);
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
              <label className="text-sm text-muted">Phone number <span className="text-xs">(change needs approval)</span></label>
              <input className="input-field mt-1 opacity-60" value={user?.phone || "—"} disabled />
            </div>
          </div>
          <p className="text-xs text-muted">To change your <b>email</b>, <b>phone number</b>, or <b>wallet address</b>, use the <b className="text-gold-light">Email, Phone &amp; Wallet Change</b> section below (TAC + admin approval required).</p>

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
            <label className="text-sm text-muted flex items-center gap-2"><Wallet size={15} className="text-gold-light" /> USDT Withdrawal Address <span className="text-xs">(change needs approval)</span> <span className="text-gold-light text-xs">(BEP20)</span></label>
            <input className="input-field mt-1 opacity-60" value={user?.wallet_address || "—"} disabled />
            <p className="text-xs text-muted mt-1">You can change this <b className="text-foreground">anytime</b> in the <b className="text-gold-light">Email, Phone &amp; Wallet Change</b> section below — a TAC + admin/staff approval is required.</p>
          </div>

          <button disabled={pLoad} className="btn-gold px-6 py-2.5">{pLoad ? "Saving…" : "Save Profile"}</button>
        </form>
      </div>

      {/* Email / wallet change requests */}
      <div className="glass p-6">
        <h3 className="font-semibold flex items-center gap-2"><Wallet size={18} className="text-gold-light" /> Email, Phone &amp; Wallet Change</h3>
        <p className="text-sm text-muted mt-1">Each change needs a 6-digit code (TAC) sent to your <b>current email</b>, then admin/staff approval before it takes effect.</p>

        <div className="mt-5 grid sm:grid-cols-2 gap-5">
          <ChangeRequestForm field="email" label="New Email" current={user?.email || "—"} placeholder="you@email.com" inputType="email" onDone={loadRequests} />
          <ChangeRequestForm field="phone" label="New Phone Number" current={user?.phone || "—"} placeholder="+60123456789" inputType="tel" onDone={loadRequests} />
          <ChangeRequestForm field="wallet_address" label="New USDT Address (BEP20)" current={user?.wallet_address || "—"} placeholder="0x… (BEP20 / BSC)" onDone={loadRequests} />
        </div>

        {requests.length > 0 && (
          <div className="mt-6 border-t border-[var(--line)] pt-4">
            <p className="text-sm font-medium mb-2">Your recent requests</p>
            <div className="space-y-2">
              {requests.map((r) => (
                <div key={r.id} className="flex items-center justify-between gap-3 text-sm bg-black/30 rounded-lg px-3 py-2">
                  <span className="text-muted">{r.field === "email" ? "Email" : r.field === "phone" ? "Phone" : "Wallet"} → <span className="text-foreground break-all">{r.new_value}</span></span>
                  <CRStatus status={r.status} />
                </div>
              ))}
            </div>
          </div>
        )}
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
                  <label className="text-sm text-muted">Country of ID <span className="text-red-500">*</span></label>
                  <select className="input-field mt-1" value={kyc.kyc_country} onChange={(e) => setKyc({ ...kyc, kyc_country: e.target.value })}>
                    {COUNTRIES.map((c) => (
                      <option key={c.iso} value={c.iso}>{c.flag} {c.name}</option>
                    ))}
                  </select>
                </div>
                <div>
                  <label className="text-sm text-muted">ID type <span className="text-red-500">*</span></label>
                  <select className="input-field mt-1" value={kyc.id_type} onChange={(e) => setKyc({ ...kyc, id_type: e.target.value })}>
                    <option value="ic">National ID Card (IC)</option>
                    <option value="passport">Passport</option>
                    <option value="license">Driving License</option>
                  </select>
                </div>
              </div>
              <div>
                <label className="text-sm text-muted">ID number <span className="text-red-500">*</span></label>
                <input className="input-field mt-1" value={kyc.id_number} onChange={(e) => setKyc({ ...kyc, id_number: e.target.value })} required />
                <p className="text-xs text-muted mt-1">
                  {kyc.id_type === "passport" ? "Passport — 5–12 letters/digits"
                    : kyc.id_type === "license" ? "Driving licence number as printed"
                    : idFormatHint(kyc.kyc_country)}
                </p>
              </div>
              <div>
                <label className="text-sm text-muted">Upload ID document <span className="text-red-500">*</span> <span className="text-xs">(valid &amp; not expired · image or PDF, max 20MB)</span></label>
                <input type="file" accept="image/*,.pdf,.heic,.heif" className="input-field mt-1" onChange={(e) => setKyc({ ...kyc, document: e.target.files?.[0] || null })} required />
              </div>
              <div>
                <label className="text-sm text-muted">Upload selfie <span className="text-red-500">*</span> <span className="text-xs">(a clear photo of your face · take a photo or choose from gallery, max 20MB)</span></label>
                <input type="file" accept="image/*,.heic,.heif" className="input-field mt-1" onChange={(e) => setKyc({ ...kyc, selfie: e.target.files?.[0] || null })} required />
                <p className="text-xs text-muted mt-1">Our team compares your selfie with the photo on your ID. Both images are stamped with a Regal Markets watermark for your protection.</p>
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

interface ChangeReq { id: number; field: string; new_value: string; status: string; }

function CRStatus({ status }: { status: string }) {
  const map: Record<string, string> = {
    pending_tac: "bg-white/10 text-muted",
    pending: "bg-yellow-500/15 text-yellow-400",
    approved: "bg-green-500/15 text-green-400",
    rejected: "bg-red-500/15 text-red-400",
  };
  const label: Record<string, string> = {
    pending_tac: "Awaiting code",
    pending: "Pending approval",
    approved: "Approved",
    rejected: "Rejected",
  };
  return <span className={`shrink-0 text-xs px-2 py-0.5 rounded-full ${map[status] || "bg-white/10 text-muted"}`}>{label[status] || status}</span>;
}

function ChangeRequestForm({
  field, label, current, placeholder, inputType = "text", onDone,
}: {
  field: "email" | "wallet_address" | "phone";
  label: string;
  current: string;
  placeholder: string;
  inputType?: string;
  onDone?: () => void;
}) {
  const [step, setStep] = useState<"idle" | "tac">("idle");
  const [newValue, setNewValue] = useState("");
  const [reqId, setReqId] = useState<number | null>(null);
  const [code, setCode] = useState("");
  const [msg, setMsg] = useState(""); const [err, setErr] = useState(""); const [loading, setLoading] = useState(false);
  const refresh = useAuth((s) => s.refresh);
  const isFirstTime = field === "wallet_address" && (!current || current === "—");

  async function submitNew(e: React.FormEvent) {
    e.preventDefault(); setMsg(""); setErr(""); setLoading(true);
    try {
      const { data } = await api.post("/account-changes", { field, new_value: newValue });
      if (data.applied) {
        setMsg(data.message); setNewValue("");
        await refresh?.(); onDone?.();
        return;
      }
      setReqId(data.request_id); setStep("tac"); setMsg(data.message);
    } catch (e) { setErr(apiError(e)); } finally { setLoading(false); }
  }
  async function confirmTac(e: React.FormEvent) {
    e.preventDefault(); setMsg(""); setErr(""); setLoading(true);
    try {
      const { data } = await api.post("/account-changes/verify-tac", { request_id: reqId, code });
      setMsg(data.message); setStep("idle"); setNewValue(""); setCode(""); setReqId(null);
      onDone?.();
    } catch (e) { setErr(apiError(e)); } finally { setLoading(false); }
  }
  async function resend() {
    setErr(""); try { const { data } = await api.post("/account-changes/resend-tac", { request_id: reqId }); setMsg(data.message); } catch (e) { setErr(apiError(e)); }
  }

  // Live BEP20 format check for the wallet address field (0x + 40 hex).
  const walletCheck = (() => {
    if (field !== "wallet_address") return null;
    const v = newValue.trim();
    if (v === "") return null;
    if (!/^0x/i.test(v)) return { ok: false, typing: false, msg: "Must start with 0x." };
    if (/[^0-9a-fA-F]/.test(v.slice(2))) return { ok: false, typing: false, msg: "Invalid characters — only 0-9 and a-f allowed." };
    if (v.length < 42) return { ok: false, typing: true, msg: `Too short — need 42 characters (0x + 40). Now ${v.length}.` };
    if (v.length > 42) return { ok: false, typing: false, msg: `Too long — need 42 characters. Now ${v.length}.` };
    return { ok: true, typing: false, msg: "✓ Valid BEP20 (BSC) address format." };
  })();
  const walletBlocks = field === "wallet_address" && !!newValue.trim() && !!walletCheck && !walletCheck.ok;

  return (
    <div className="bg-black/20 rounded-xl p-4 border border-[var(--line)]">
      <label className="text-sm font-medium">{label}</label>
      <p className="text-xs text-muted mt-0.5 break-all">Current: {current}</p>
      {field === "wallet_address" && (
        <p className={`text-[11px] mb-2 ${isFirstTime ? "text-green-400" : "text-yellow-400"}`}>
          {isFirstTime ? "First-time — saved instantly, no approval needed." : "Changing needs admin approval (security)."}
        </p>
      )}
      {field === "email" && <div className="mb-1" />}
      {msg && <div className="mb-2 text-xs text-green-400 bg-green-500/10 border border-green-500/30 rounded-lg px-3 py-2">{msg}</div>}
      {err && <div className="mb-2 text-xs text-red-400 bg-red-500/10 border border-red-500/30 rounded-lg px-3 py-2">{err}</div>}

      {step === "idle" ? (
        <form onSubmit={submitNew} className="space-y-2">
          <input type={inputType} className="input-field" style={walletCheck ? { borderColor: walletCheck.ok ? "#4ade80" : walletCheck.typing ? "#facc15" : "#f87171" } : undefined}
            placeholder={placeholder} value={newValue} onChange={(e) => setNewValue(e.target.value)} required />
          {walletCheck && (
            <p className={`text-[11px] flex items-start gap-1 ${walletCheck.ok ? "text-green-400" : walletCheck.typing ? "text-yellow-400" : "text-red-400"}`}>
              <span>{walletCheck.ok ? "✓" : walletCheck.typing ? "…" : "⚠"}</span><span>{walletCheck.msg}</span>
            </p>
          )}
          <button disabled={loading || !newValue || walletBlocks} className="btn-gold w-full py-2 text-sm disabled:opacity-50">{loading ? "Saving…" : (isFirstTime ? "Save address" : "Request change → send code")}</button>
        </form>
      ) : (
        <form onSubmit={confirmTac} className="space-y-2">
          <input inputMode="numeric" maxLength={6} className="input-field text-center tracking-[0.4em] font-mono" placeholder="······" value={code} onChange={(e) => setCode(e.target.value.replace(/\D/g, ""))} required />
          <div className="flex gap-2">
            <button disabled={loading || code.length < 6} className="btn-gold flex-1 py-2 text-sm disabled:opacity-50">{loading ? "Confirming…" : "Confirm"}</button>
            <button type="button" onClick={resend} className="btn-ghost px-3 py-2 text-xs">Resend</button>
            <button type="button" onClick={() => { setStep("idle"); setCode(""); setMsg(""); setErr(""); }} className="btn-ghost px-3 py-2 text-xs">Cancel</button>
          </div>
        </form>
      )}
    </div>
  );
}
