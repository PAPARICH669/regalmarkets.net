"use client";

import { useEffect, useState } from "react";
import { usePathname, useRouter } from "next/navigation";
import Link from "next/link";
import {
  LayoutDashboard, Repeat, Network, LogOut, Menu, X, Wallet, UserCog, ArrowLeftRight, ShieldCheck, UserCheck, History,
} from "lucide-react";
import Logo from "@/components/Logo";
import IdleLogout from "@/components/IdleLogout";
import MarketTicker from "@/components/MarketTicker";
import BottomNav from "@/components/BottomNav";
import { useAuth } from "@/lib/auth";

// Conditional items: `ld` (LD members), `kyc`/`cr` (assigned staff duties).
const NAV = [
  { href: "/dashboard", label: "Dashboard", icon: LayoutDashboard, mobileHide: true },
  { href: "/fund", label: "Fund", icon: Repeat },
  { href: "/activity", label: "Activity", icon: History },
  { href: "/transfer", label: "Transfer", icon: ArrowLeftRight, ld: true },
  { href: "/kyc-review", label: "KYC Review", icon: UserCheck, kyc: true },
  { href: "/change-requests", label: "Change Requests", icon: UserCheck, cr: true },
  { href: "/network", label: "Team Network", icon: Network },
  { href: "/wallet", label: "Wallet", icon: Wallet },
  { href: "/kyc", label: "KYC", icon: ShieldCheck },
  { href: "/profile", label: "Profile & Security", icon: UserCog },
];

export default function DashboardLayout({ children }: { children: React.ReactNode }) {
  const router = useRouter();
  const pathname = usePathname();
  const { user, loading, hydrated, bootstrap, logout } = useAuth();
  const [open, setOpen] = useState(false);

  useEffect(() => { bootstrap(); }, [bootstrap]);
  useEffect(() => {
    if (hydrated && !loading && !user) router.replace("/login");
  }, [hydrated, loading, user, router]);

  if (!hydrated || (!user && loading)) {
    return <div className="min-h-screen grid place-items-center text-muted">Loading…</div>;
  }
  if (!user) return null;

  return (
    <div className="min-h-screen flex relative">
      <IdleLogout />
      {/* Background logo watermark */}
      {/* eslint-disable-next-line @next/next/no-img-element */}
      <img
        src="/logo-watermark.png"
        alt=""
        aria-hidden="true"
        className="pointer-events-none select-none fixed inset-0 m-auto w-[min(70vw,640px)] max-w-none opacity-[0.10] z-20"
      />

      {/* Sidebar */}
      <aside className={`fixed lg:static z-50 w-64 h-screen lg:h-auto lg:min-h-screen bg-[var(--surface)] border-r border-[var(--line)] flex flex-col transition-transform ${open ? "translate-x-0" : "-translate-x-full lg:translate-x-0"}`}>
        <div className="h-16 flex items-center px-5 border-b border-[var(--line)]">
          <Logo size="sm" />
        </div>
        <nav className="flex-1 p-3 space-y-1 overflow-y-auto">
          {NAV.filter((item) => (!item.ld || user.is_ld) && (!item.kyc || user.can_kyc) && (!item.cr || user.can_cr)).map((item) => {
            const active = pathname === item.href;
            const Icon = item.icon;
            return (
              <Link key={item.href} href={item.href} onClick={() => setOpen(false)}
                className={`${item.mobileHide ? "hidden lg:flex" : "flex"} items-center gap-3 px-3 py-2.5 rounded-lg text-sm transition ${active ? "gold-gradient text-black font-semibold" : "text-muted hover:bg-white/5 hover:text-gold-light"}`}>
                <Icon size={18} /> {item.label}
              </Link>
            );
          })}
          {user.is_admin && (
            <Link href="/admin" className="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm text-gold-light hover:bg-white/5">
              <Wallet size={18} /> Admin Panel
            </Link>
          )}
          <button onClick={() => logout().then(() => router.replace("/login"))}
            className="w-full flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm text-red-400 hover:bg-red-500/10">
            <LogOut size={18} /> Logout
          </button>
        </nav>
      </aside>

      {open && <div className="fixed inset-0 bg-black/60 z-40 lg:hidden" onClick={() => setOpen(false)} />}

      {/* Main */}
      <div className="flex-1 min-w-0 relative z-10">
        <MarketTicker />
        <header className="h-16 border-b border-[var(--line)] flex items-center justify-between px-5 sticky top-0 bg-[rgba(4,16,42,0.85)] backdrop-blur-md z-30">
          <button className="lg:hidden btn-ghost p-2" onClick={() => setOpen(true)}><Menu size={18} /></button>
        </header>
        <div className="p-5 pb-28 lg:pb-5 max-w-7xl mx-auto">{children}</div>
      </div>

      <BottomNav />
    </div>
  );
}
