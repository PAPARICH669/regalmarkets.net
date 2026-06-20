"use client";

import { useEffect, useState } from "react";
import { usePathname, useRouter } from "next/navigation";
import Link from "next/link";
import {
  LayoutDashboard, ArrowDownToLine, ArrowUpFromLine, Users, Settings,
  Megaphone, Wrench, Layers, FileText, LogOut, Menu, ShieldCheck, TrendingUp, UserCog, Gift, History,
} from "lucide-react";
import Logo from "@/components/Logo";
import IdleLogout from "@/components/IdleLogout";
import api from "@/lib/api";
import { useAuth } from "@/lib/auth";

// `staff: true` items are visible to limited staff users; the rest are admin-only.
const NAV = [
  { href: "/admin", label: "Overview", icon: LayoutDashboard },
  { href: "/admin/deposits", label: "Deposits", icon: ArrowDownToLine },
  { href: "/admin/withdrawals", label: "Withdrawals", icon: ArrowUpFromLine },
  { href: "/admin/members", label: "Members", icon: Users, staff: true },
  { href: "/admin/kyc", label: "KYC", icon: ShieldCheck, staff: true },
  { href: "/admin/change-requests", label: "Change Requests", icon: UserCog, staff: true },
  { href: "/admin/sponsor-rewards", label: "Sponsor Rewards", icon: Gift },
  { href: "/admin/roi", label: "Daily Commission", icon: TrendingUp },
  { href: "/admin/logs", label: "Bonus Logs", icon: Layers },
  { href: "/admin/wallet-adjustments", label: "Wallet Adjustments", icon: History },
  { href: "/admin/settings", label: "Settings", icon: Settings },
  { href: "/admin/announcements", label: "Announcements", icon: Megaphone },
  { href: "/admin/maintenance", label: "Maintenance", icon: Wrench },
  { href: "/admin/reports", label: "Reports", icon: FileText },
];

export default function AdminLayout({ children }: { children: React.ReactNode }) {
  const router = useRouter();
  const pathname = usePathname();
  const { user, loading, hydrated, bootstrap, logout } = useAuth();
  const [open, setOpen] = useState(false);

  const isStaffOnly = !!user && !user.is_admin && !!user.is_staff;

  // Pending counts for the notification badges (verify + approvals).
  const [counts, setCounts] = useState({ deposits: 0, withdrawals: 0, kyc: 0, change_requests: 0, sponsor_rewards: 0 });
  const countFor = (href: string) =>
    href === "/admin/deposits" ? counts.deposits
    : href === "/admin/withdrawals" ? counts.withdrawals
    : href === "/admin/kyc" ? counts.kyc
    : href === "/admin/change-requests" ? counts.change_requests
    : href === "/admin/sponsor-rewards" ? counts.sponsor_rewards
    : 0;

  useEffect(() => { bootstrap(); }, [bootstrap]);
  useEffect(() => {
    if (!user || (!user.is_admin && !user.is_staff)) return;
    let alive = true;
    const fetchCounts = () =>
      api.get("/admin/pending-counts").then((r) => { if (alive) setCounts(r.data); }).catch(() => {});
    fetchCounts();
    const id = setInterval(fetchCounts, 45000); // refresh every 45s
    return () => { alive = false; clearInterval(id); };
  }, [user, pathname]);
  useEffect(() => {
    if (hydrated && !loading) {
      if (!user) router.replace("/login");
      else if (!user.is_admin && !user.is_staff) router.replace("/dashboard");
    }
  }, [hydrated, loading, user, router]);

  if (!hydrated || loading) return <div className="min-h-screen grid place-items-center text-muted">Loading…</div>;
  if (!user || (!user.is_admin && !user.is_staff)) return null;

  const navItems = isStaffOnly ? NAV.filter((n) => n.staff) : NAV;

  return (
    <div className="min-h-screen flex">
      <IdleLogout />
      <aside className={`fixed lg:static z-50 w-64 h-screen lg:min-h-screen bg-[var(--surface)] border-r border-[var(--line)] flex flex-col transition-transform ${open ? "translate-x-0" : "-translate-x-full lg:translate-x-0"}`}>
        <div className="h-16 flex items-center px-5 border-b border-[var(--line)] gap-2">
          <Logo size="sm" />
        </div>
        <div className="px-5 py-2 text-xs uppercase tracking-wider text-gold-light flex items-center gap-1"><ShieldCheck size={13} /> {isStaffOnly ? "Staff" : "Admin"}</div>
        <nav className="flex-1 p-3 space-y-1 overflow-y-auto">
          {navItems.map((item) => {
            const active = pathname === item.href;
            const Icon = item.icon;
            const count = countFor(item.href);
            return (
              <Link key={item.href} href={item.href} onClick={() => setOpen(false)}
                className={`flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm transition ${active ? "gold-gradient text-black font-semibold" : "text-muted hover:bg-white/5 hover:text-gold-light"}`}>
                <Icon size={18} /> <span className="flex-1">{item.label}</span>
                {count > 0 && (
                  <span className={`min-w-[20px] h-5 px-1.5 grid place-items-center rounded-full text-[11px] font-bold ${active ? "bg-black/25 text-black" : "bg-red-500 text-white"}`}>
                    {count}
                  </span>
                )}
              </Link>
            );
          })}
          <button onClick={() => logout().then(() => router.replace("/login"))}
            className="w-full flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm text-red-400 hover:bg-red-500/10">
            <LogOut size={18} /> Logout
          </button>
        </nav>
      </aside>

      {open && <div className="fixed inset-0 bg-black/60 z-40 lg:hidden" onClick={() => setOpen(false)} />}

      <div className="flex-1 min-w-0">
        <header className="h-16 border-b border-[var(--line)] flex items-center justify-between px-5 sticky top-0 bg-[rgba(4,16,42,0.85)] backdrop-blur-md z-30">
          <button className="lg:hidden btn-ghost p-2" onClick={() => setOpen(true)}><Menu size={18} /></button>
          <span className="ml-auto text-sm text-muted">{user.username} · {isStaffOnly ? "Staff" : "Administrator"}</span>
        </header>
        <div className="p-5 max-w-7xl mx-auto">{children}</div>
      </div>
    </div>
  );
}
