"use client";

import Link from "next/link";
import { ShieldAlert } from "lucide-react";
import { useAuth } from "@/lib/auth";

export default function KycBanner({ action }: { action: string }) {
  const user = useAuth((s) => s.user);
  if (!user || user.is_admin || user.kyc_status === "verified") return null;

  const status = user.kyc_status || "unsubmitted";
  const text =
    status === "pending"
      ? "Your KYC is pending admin review. You can't " + action + " until it's verified."
      : status === "rejected"
        ? `KYC was rejected${user.kyc_note ? `: ${user.kyc_note}` : ""}. Please resubmit to ${action}.`
        : `Complete KYC verification before you can ${action}.`;

  return (
    <div className="glass border border-yellow-500/30 p-4 flex items-center justify-between gap-3 flex-wrap">
      <div className="flex items-center gap-2 text-sm text-yellow-300">
        <ShieldAlert size={18} /> {text}
      </div>
      <Link href="/profile" className="btn-gold px-4 py-2 text-sm shrink-0">Complete KYC</Link>
    </div>
  );
}
