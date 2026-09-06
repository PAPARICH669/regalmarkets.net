"use client";

import { useEffect, useState } from "react";
import { ShieldCheck } from "lucide-react";
import api, { apiError } from "@/lib/api";
import { useAuth } from "@/lib/auth";
import { COUNTRIES, idFormatHint } from "@/lib/countries";

const KYC_BADGE: Record<string, string> = {
  unsubmitted: "bg-white/10 text-muted",
  pending: "bg-yellow-500/15 text-yellow-400",
  verified: "bg-green-500/15 text-green-400",
  rejected: "bg-red-500/15 text-red-400",
};

export default function KycPage() {
  const { user, refresh } = useAuth();
  const [kyc, setKyc] = useState<{ kyc_country: string; id_type: string; id_number: string; document: File | null; selfie: File | null }>(
    { kyc_country: "MY", id_type: "ic", id_number: "", document: null, selfie: null });
  const [kMsg, setKMsg] = useState(""); const [kErr, setKErr] = useState(""); const [kLoad, setKLoad] = useState(false);

  useEffect(() => {
    if (user) {
      const iso = (user.country || "").toUpperCase();
      if (COUNTRIES.some((c) => c.iso === iso)) setKyc((k) => ({ ...k, kyc_country: iso }));
    }
  }, [user]);

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
      <h1 className="text-2xl font-bold flex items-center gap-2"><ShieldCheck size={22} className="text-gold-light" /> KYC Verification</h1>

      <div className="glass p-6">
        <div className="flex items-center justify-between">
          <h3 className="font-semibold">Identity Verification</h3>
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
                    {COUNTRIES.map((c) => (<option key={c.iso} value={c.iso}>{c.flag} {c.name}</option>))}
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
    </div>
  );
}
