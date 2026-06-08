"use client";

import { useEffect, useState } from "react";
import { Network as NetIcon } from "lucide-react";
import api from "@/lib/api";
import { usdt, shortDate } from "@/lib/format";

type Tab = "roi" | "sponsor" | "matching" | "group";

interface LevelRow { level: number; members: number; sales: number; invested: number; }
interface Report {
  group_sales_by_level: LevelRow[];
  total_group_sales: number;
  total_group_members: number;
}
interface FromUser { id: number; username: string; name?: string; rank?: { name: string } }
interface SponsorLog { id: number; from_user?: FromUser; level: number; percent: string; amount: string; created_at: string; }
interface MatchLog { id: number; from_user?: FromUser; upline_rank: string; applied_percent: string; roi_amount: string; amount: string; created_at: string; }
interface RoiLog { id: number; amount: string; roi_date: string; package?: { id: number; principal: string } }

const TABS: { key: Tab; label: string }[] = [
  { key: "roi", label: "Daily Commission" },
  { key: "sponsor", label: "Sponsor Bonus" },
  { key: "matching", label: "Matching Bonus" },
  { key: "group", label: "Group Sales" },
];

function FromCell({ u }: { u?: FromUser }) {
  if (!u) return <span className="text-muted">—</span>;
  return (
    <div className="leading-tight">
      <div className="font-medium">{u.name || u.username}</div>
      <div className="text-xs text-muted">@{u.username}</div>
    </div>
  );
}

export default function ReportsPage() {
  const [tab, setTab] = useState<Tab>("roi");
  const [r, setR] = useState<Report | null>(null);
  const [roi, setRoi] = useState<RoiLog[]>([]);
  const [sponsor, setSponsor] = useState<SponsorLog[]>([]);
  const [matching, setMatching] = useState<MatchLog[]>([]);
  const [loading, setLoading] = useState(false);

  // Group-sales structure (network) — fetched once.
  useEffect(() => { api.get("/reports/daily").then((res) => setR(res.data)); }, []);

  useEffect(() => {
    setLoading(true);
    const done = () => setLoading(false);
    if (tab === "roi") api.get("/logs/roi").then((res) => setRoi(res.data.data)).finally(done);
    else if (tab === "sponsor") api.get("/logs/sponsor").then((res) => setSponsor(res.data.data)).finally(done);
    else if (tab === "matching") api.get("/logs/matching").then((res) => setMatching(res.data.data)).finally(done);
    else done();
  }, [tab]);

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold">Reports</h1>
        <p className="text-muted text-sm">Your records from the first commission &amp; bonus you earned.</p>
      </div>

      {/* Report selector buttons */}
      <div className="flex flex-wrap gap-2">
        {TABS.map((t) => (
          <button key={t.key} onClick={() => setTab(t.key)}
            className={`px-4 py-2 rounded-lg text-sm transition ${tab === t.key ? "gold-gradient text-black font-semibold" : "btn-ghost"}`}>
            {t.label}
          </button>
        ))}
      </div>

      {/* ---- Daily Commission detail ---- */}
      {tab === "roi" && (
        <div className="glass p-5">
          <h3 className="font-semibold mb-4">Daily Commission</h3>
          <ReportTable head={["Date", "Package", "Amount"]} loading={loading} empty={roi.length === 0}>
            {roi.map((x) => (
              <tr key={x.id} className="border-t border-[var(--line)]">
                <td className="py-2">{shortDate(x.roi_date).split(",")[0]}</td>
                <td className="text-muted">Package #{x.package?.id} · {usdt(x.package?.principal)}</td>
                <td className="gold-text font-medium">{usdt(x.amount)}</td>
              </tr>
            ))}
          </ReportTable>
        </div>
      )}

      {/* ---- Sponsor Bonus detail ---- */}
      {tab === "sponsor" && (
        <div className="glass p-5">
          <h3 className="font-semibold mb-1">Sponsor Bonus</h3>
          <p className="text-sm text-muted mb-4">Earned instantly when a downline funds a package.</p>
          <ReportTable head={["From Member", "Level", "%", "Amount", "Date"]} loading={loading} empty={sponsor.length === 0}>
            {sponsor.map((x) => (
              <tr key={x.id} className="border-t border-[var(--line)]">
                <td className="py-2"><FromCell u={x.from_user} /></td>
                <td><span className="rank-badge">L{x.level}</span></td>
                <td>{Number(x.percent)}%</td>
                <td className="gold-text font-medium">{usdt(x.amount)}</td>
                <td className="text-muted text-xs">{shortDate(x.created_at)}</td>
              </tr>
            ))}
          </ReportTable>
        </div>
      )}

      {/* ---- Matching Bonus detail ---- */}
      {tab === "matching" && (
        <div className="glass p-5">
          <h3 className="font-semibold mb-1">Matching Bonus</h3>
          <p className="text-sm text-muted mb-4">Differential override on each downline&apos;s daily Commission.</p>
          <ReportTable head={["From Member", "Member Rank", "% × Passive Profit", "Amount", "Date"]} loading={loading} empty={matching.length === 0}>
            {matching.map((x) => (
              <tr key={x.id} className="border-t border-[var(--line)]">
                <td className="py-2"><FromCell u={x.from_user} /></td>
                <td className="text-xs text-muted">{x.from_user?.rank?.name ?? "—"}</td>
                <td className="leading-tight">
                  <div>{Number(x.applied_percent)}%</div>
                  <div className="text-xs text-muted">× {usdt(x.roi_amount)}</div>
                </td>
                <td className="gold-text font-medium">{usdt(x.amount)}</td>
                <td className="text-muted text-xs">{shortDate(x.created_at)}</td>
              </tr>
            ))}
          </ReportTable>
        </div>
      )}

      {/* ---- Group Total Invest by level ---- */}
      {tab === "group" && (() => {
        const levels = r?.group_sales_by_level ?? [];
        const totalInvest = levels.reduce((sum, l) => sum + Number(l.invested || 0), 0);
        return (
        <div className="glass p-5">
          <div className="flex items-center justify-between flex-wrap gap-2 mb-4">
            <h3 className="font-semibold flex items-center gap-2"><NetIcon size={18} className="text-gold-light" /> Group Sales by Level</h3>
            <div className="text-right">
              <p className="text-xs text-muted">Total Invest</p>
              <p className="text-lg font-bold gold-text">{usdt(totalInvest)} <span className="text-xs text-muted font-normal">· {r?.total_group_members ?? 0} members</span></p>
            </div>
          </div>
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead className="text-gold-light text-left"><tr><th className="py-2">Level</th><th>Members</th><th>Total Invest</th></tr></thead>
              <tbody>
                {levels.map((l) => (
                  <tr key={l.level} className="border-t border-[var(--line)]">
                    <td className="py-2">Level {l.level}</td><td>{l.members}</td>
                    <td className="gold-text">{usdt(l.invested)}</td>
                  </tr>
                ))}
                {levels.length === 0 && <tr><td colSpan={3} className="py-4 text-center text-muted">No downline invest yet.</td></tr>}
              </tbody>
              {levels.length > 0 && (
                <tfoot><tr className="border-t border-[var(--line)] font-semibold"><td className="py-2">Total</td><td>{r?.total_group_members ?? 0}</td><td className="gold-text">{usdt(totalInvest)}</td></tr></tfoot>
              )}
            </table>
          </div>
        </div>
        );
      })()
      )}
    </div>
  );
}

function ReportTable({ head, children, loading, empty }: { head: string[]; children: React.ReactNode; loading: boolean; empty: boolean }) {
  return (
    <div className="overflow-x-auto max-h-[480px] overflow-y-auto">
      <table className="w-full text-sm">
        <thead className="text-gold-light text-left sticky top-0 bg-[var(--surface)]">
          <tr>{head.map((h) => <th key={h} className="py-2">{h}</th>)}</tr>
        </thead>
        <tbody>
          {children}
          {loading && <tr><td colSpan={head.length} className="py-4 text-center text-muted">Loading…</td></tr>}
          {!loading && empty && <tr><td colSpan={head.length} className="py-4 text-center text-muted">No records yet.</td></tr>}
        </tbody>
      </table>
    </div>
  );
}
