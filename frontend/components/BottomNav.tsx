"use client";

import { useEffect, useState } from "react";
import { usePathname } from "next/navigation";
import Link from "next/link";
import {
  LayoutDashboard, ArrowDownCircle, ArrowUpCircle, CircleUser, QrCode,
  Copy, Check, Share2, Download,
} from "lucide-react";
import { QRCodeCanvas } from "qrcode.react";
import { useAuth } from "@/lib/auth";

/**
 * Mobile bottom navigation bar (hidden on lg+, where the sidebar is used).
 * Dashboard · Deposit · [My QR] · Withdrawal · Profile. The raised center button
 * opens a sheet with the member's referral QR + link (copy / share / save).
 */
export default function BottomNav() {
  const pathname = usePathname();
  const user = useAuth((s) => s.user);
  const [open, setOpen] = useState(false);
  const [copied, setCopied] = useState(false);
  const [refLink, setRefLink] = useState("");

  useEffect(() => {
    if (user) setRefLink(`${window.location.origin}/register?ref=${user.referral_code || user.username}`);
  }, [user]);

  if (!user) return null;
  const isActive = (href: string) => pathname === href;

  async function copy() {
    try { await navigator.clipboard.writeText(refLink); setCopied(true); setTimeout(() => setCopied(false), 1500); } catch {}
  }
  async function share() {
    const text = `Sertai Regal Markets 🚀\n${refLink}`;
    if (typeof navigator !== "undefined" && (navigator as Navigator).share) {
      try { await (navigator as Navigator).share({ title: "Regal Markets", text, url: refLink }); } catch {}
    } else { copy(); }
  }
  function saveQr() {
    const canvas = document.getElementById("myqr-canvas") as HTMLCanvasElement | null;
    if (!canvas) return;
    const a = document.createElement("a");
    a.href = canvas.toDataURL("image/png");
    a.download = "regal-referral-qr.png";
    a.click();
  }

  return (
    <>
      <nav className="lg:hidden fixed bottom-0 left-0 right-0 z-40 bg-[#071a3d] border-t border-[var(--line)] flex items-end justify-between px-3 pt-2 pb-3">
        <NavItem href="/dashboard" active={isActive("/dashboard")} icon={<LayoutDashboard size={21} />} label="Dashboard" />
        <NavItem href="/deposit" active={isActive("/deposit")} icon={<ArrowDownCircle size={21} />} label="Deposit" />

        <button onClick={() => setOpen(true)} aria-label="My QR" className="flex flex-col items-center gap-1 shrink-0" style={{ width: 56 }}>
          <span className="gold-gradient flex items-center justify-center rounded-full border-4 border-[#04102a]" style={{ width: 52, height: 52, marginTop: -28 }}>
            <QrCode size={26} className="text-black" />
          </span>
          <span className="text-[9px] text-gold-light font-semibold">My QR</span>
        </button>

        <NavItem href="/withdraw" active={isActive("/withdraw")} icon={<ArrowUpCircle size={21} />} label="Withdrawal" />
        <NavItem href="/profile" active={isActive("/profile")} icon={<CircleUser size={21} />} label="Profile" />
      </nav>

      {open && (
        <div className="lg:hidden fixed inset-0 z-50">
          <div className="absolute inset-0 bg-black/60" onClick={() => setOpen(false)} />
          <div className="absolute left-0 right-0 bottom-0 bg-[#0a1c40] border-t border-gold-light/30 rounded-t-2xl p-5 pb-8 animate-fade-up">
            <div className="w-10 h-1 bg-white/20 rounded-full mx-auto mb-4" />
            <h3 className="text-center font-semibold">My Referral QR</h3>
            <p className="text-center text-xs text-muted mb-4">New members scan to register under you</p>

            <div className="flex justify-center mb-4">
              <div className="bg-white p-3 rounded-xl leading-none">
                {refLink && <QRCodeCanvas id="myqr-canvas" value={refLink} size={150} level="M" />}
              </div>
            </div>

            <div className="flex gap-2 items-center mb-3">
              <code className="flex-1 bg-[#04102a] border border-[var(--line)] rounded-lg px-3 py-2 text-xs break-all text-[#cdd9f0]">
                {refLink.replace(/^https?:\/\//, "")}
              </code>
              <button onClick={copy} className="btn-ghost px-3 py-2 text-xs shrink-0">{copied ? <Check size={14} className="text-green-400" /> : <Copy size={14} />}</button>
            </div>

            <div className="flex gap-2">
              <button onClick={share} className="btn-gold flex-1 py-2.5 text-sm flex items-center justify-center gap-1.5"><Share2 size={15} /> Share</button>
              <button onClick={saveQr} className="btn-ghost flex-1 py-2.5 text-sm flex items-center justify-center gap-1.5"><Download size={15} /> Save QR</button>
            </div>
            <button onClick={() => setOpen(false)} className="w-full mt-3 py-2 text-sm text-muted">Tutup</button>
          </div>
        </div>
      )}
    </>
  );
}

function NavItem({ href, icon, label, active }: { href: string; icon: React.ReactNode; label: string; active: boolean }) {
  return (
    <Link href={href} className={`flex flex-col items-center gap-1 ${active ? "text-gold-light" : "text-muted"}`} style={{ width: 48 }}>
      {icon}
      <span className={`text-[9px] ${active ? "font-semibold" : ""}`}>{label}</span>
    </Link>
  );
}
