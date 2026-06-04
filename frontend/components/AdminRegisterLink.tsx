"use client";

import { useState } from "react";
import { Copy, Check, UserPlus } from "lucide-react";
import { useAuth } from "@/lib/auth";

export default function AdminRegisterLink() {
  const user = useAuth((s) => s.user);
  const [copied, setCopied] = useState<"ref" | "plain" | null>(null);

  if (!user) return null;
  const origin = typeof window !== "undefined" ? window.location.origin : "https://regalmarkets.net";
  const refLink = `${origin}/register?ref=${user.referral_code}`;
  const plainLink = `${origin}/register`;

  const copy = (text: string, which: "ref" | "plain") => {
    navigator.clipboard.writeText(text);
    setCopied(which);
    setTimeout(() => setCopied(null), 1500);
  };

  return (
    <div className="glass p-5">
      <h3 className="font-semibold flex items-center gap-2"><UserPlus size={18} className="text-gold-light" /> New User Registration Link</h3>
      <p className="text-sm text-muted mt-1">Share this link to register new users. They will be sponsored by the admin and must verify their email.</p>

      <div className="mt-4">
        <p className="text-xs text-muted mb-1">Admin referral link (registrant placed under admin)</p>
        <div className="flex items-center gap-2 bg-black/30 rounded-lg p-3 text-xs break-all">
          <span className="flex-1 font-mono">{refLink}</span>
          <button onClick={() => copy(refLink, "ref")} className="btn-gold px-3 py-1.5 text-xs shrink-0 flex items-center gap-1">
            {copied === "ref" ? <Check size={14} /> : <Copy size={14} />} Copy
          </button>
        </div>
      </div>

      <div className="mt-3">
        <p className="text-xs text-muted mb-1">Plain register link (no sponsor)</p>
        <div className="flex items-center gap-2 bg-black/30 rounded-lg p-3 text-xs break-all">
          <span className="flex-1 font-mono">{plainLink}</span>
          <button onClick={() => copy(plainLink, "plain")} className="btn-ghost px-3 py-1.5 text-xs shrink-0 flex items-center gap-1">
            {copied === "plain" ? <Check size={14} /> : <Copy size={14} />} Copy
          </button>
        </div>
      </div>
    </div>
  );
}
