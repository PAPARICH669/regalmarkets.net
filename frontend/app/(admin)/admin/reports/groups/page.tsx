"use client";

import { useMemo, useState } from "react";
import { Printer, ArrowLeft, Search, Download } from "lucide-react";
import Link from "next/link";
import api, { apiError } from "@/lib/api";

interface Bucket { form: number; adjust: number; ld: number; total: number; }
interface Group { leader: string; members: number; monthly: Record<string, Bucket>; total: Bucket; }
interface View { parent: string; months: string[]; groups: Group[]; generated_at: string; }

const BULAN = ["Januari", "Februari", "Mac", "April", "Mei", "Jun", "Julai", "Ogos", "September", "Oktober", "November", "Disember"];
const monthLabel = (ym: string) => { const [y, m] = ym.split("-"); return `${BULAN[Number(m) - 1] ?? m} ${y}`; };
const fmt = (n: number) => Number(n || 0).toLocaleString("en-US", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const ZERO: Bucket = { form: 0, adjust: 0, ld: 0, total: 0 };

export default function GroupDepositPrint() {
  const [parent, setParent] = useState("");
  const [data, setData] = useState<View | null>(null);
  const [month, setMonth] = useState("all");
  const [error, setError] = useState("");
  const [busy, setBusy] = useState(false);

  async function load() {
    const p = parent.trim();
    if (!p) { setError("Masukkan username parent (cth. KOMANDO)."); return; }
    setError(""); setBusy(true);
    try {
      const res = await api.get("/admin/reports/group-deposits-view", { params: { parent: p } });
      setData(res.data); setMonth("all");
    } catch (err) { setError(apiError(err)); setData(null); } finally { setBusy(false); }
  }

  async function downloadCsv() {
    if (!data) return;
    try {
      const res = await api.get("/admin/reports/group-deposits", { params: { parent: data.parent }, responseType: "blob" });
      const url = window.URL.createObjectURL(new Blob([res.data]));
      const a = document.createElement("a");
      a.href = url; a.download = `regal_group_deposits_${data.parent}.csv`; a.click();
      window.URL.revokeObjectURL(url);
    } catch (err) { setError(apiError(err)); }
  }

  const bucketOf = (g: Group): Bucket => (month === "all" ? g.total : g.monthly[month] ?? ZERO);

  const rows = useMemo(() => {
    if (!data) return [];
    return [...data.groups].sort((a, b) => bucketOf(b).total - bucketOf(a).total);
  }, [data, month]);

  const totals = useMemo(() => rows.reduce((t, g) => {
    const b = bucketOf(g);
    return { form: t.form + b.form, adjust: t.adjust + b.adjust, ld: t.ld + b.ld, total: t.total + b.total, members: t.members + g.members };
  }, { form: 0, adjust: 0, ld: 0, total: 0, members: 0 }), [rows, month]);

  const medal = (i: number) => (i === 0 ? "🥇" : i === 1 ? "🥈" : i === 2 ? "🥉" : `${i + 1}`);

  return (
    <div className="space-y-5">
      <div className="flex items-center justify-between flex-wrap gap-3 print:hidden">
        <div className="flex items-center gap-3">
          <Link href="/admin/reports" className="btn-ghost p-2"><ArrowLeft size={16} /></Link>
          <h1 className="text-2xl font-bold">Group Deposit Report</h1>
        </div>
        {data && (
          <div className="flex gap-2">
            <button onClick={downloadCsv} className="btn-ghost px-4 py-2 flex items-center gap-2"><Download size={16} /> CSV</button>
            <button onClick={() => window.print()} className="btn-gold px-4 py-2 flex items-center gap-2"><Printer size={16} /> Print / Save PDF</button>
          </div>
        )}
      </div>

      <div className="flex flex-col sm:flex-row gap-3 print:hidden">
        <input className="input-field flex-1" placeholder="Username parent (cth. KOMANDO)" value={parent}
          onChange={(e) => setParent(e.target.value)} onKeyDown={(e) => e.key === "Enter" && load()} />
        <button onClick={load} disabled={busy} className="btn-gold px-5 py-2.5 flex items-center justify-center gap-2 shrink-0">
          <Search size={16} /> {busy ? "Memuatkan…" : "Papar"}
        </button>
      </div>

      {error && <div className="text-sm text-red-400 bg-red-500/10 border border-red-500/30 rounded-lg px-4 py-3 print:hidden">{error}</div>}

      {data && (
        <>
          {/* Month selector */}
          <div className="flex flex-wrap gap-2 print:hidden">
            <button onClick={() => setMonth("all")} className={`px-3 py-1.5 rounded-lg text-sm ${month === "all" ? "btn-gold" : "btn-ghost"}`}>Semua bulan</button>
            {data.months.map((ym) => (
              <button key={ym} onClick={() => setMonth(ym)} className={`px-3 py-1.5 rounded-lg text-sm ${month === ym ? "btn-gold" : "btn-ghost"}`}>{monthLabel(ym)}</button>
            ))}
          </div>

          {/* Print header */}
          <div className="hidden print:block mb-3">
            <h1 className="text-xl font-bold">Regal Markets — Laporan Deposit Group</h1>
            <p className="text-sm">Parent: @{data.parent} · Tempoh: {month === "all" ? "Semua bulan" : monthLabel(month)} · Dijana: {data.generated_at} · Akaun dummy dikecualikan</p>
          </div>

          <div className="glass p-4 sm:p-6 overflow-x-auto print:bg-white print:text-black">
            <div className="text-sm font-medium mb-3 print:hidden">
              Group di bawah <b className="text-gold-light">@{data.parent}</b> · {month === "all" ? "Semua bulan" : monthLabel(month)}
            </div>
            <table className="w-full text-sm">
              <thead>
                <tr className="text-left text-gold-light print:text-black border-b border-[var(--line)]">
                  <th className="py-2 pr-2">#</th>
                  <th className="py-2 pr-3">Group (leader)</th>
                  <th className="py-2 px-3 text-right">Ahli</th>
                  <th className="py-2 px-3 text-right">Deposit borang</th>
                  <th className="py-2 px-3 text-right">Adjust admin</th>
                  <th className="py-2 px-3 text-right">Transfer LD</th>
                  <th className="py-2 pl-3 text-right">Jumlah (USDT)</th>
                </tr>
              </thead>
              <tbody>
                {rows.map((g, i) => {
                  const b = bucketOf(g);
                  return (
                    <tr key={g.leader} className="border-b border-[var(--line)]/60">
                      <td className="py-2 pr-2">{medal(i)}</td>
                      <td className="py-2 pr-3 font-medium whitespace-nowrap">{g.leader}</td>
                      <td className="py-2 px-3 text-right">{g.members}</td>
                      <td className="py-2 px-3 text-right">{fmt(b.form)}</td>
                      <td className="py-2 px-3 text-right">{fmt(b.adjust)}</td>
                      <td className="py-2 px-3 text-right">{fmt(b.ld)}</td>
                      <td className="py-2 pl-3 text-right font-semibold">{fmt(b.total)}</td>
                    </tr>
                  );
                })}
                {rows.length === 0 && <tr><td colSpan={7} className="py-6 text-center text-muted">Tiada group.</td></tr>}
              </tbody>
              {rows.length > 0 && (
                <tfoot>
                  <tr className="border-t-2 border-[var(--line)] font-bold">
                    <td className="py-3 pr-2"></td>
                    <td className="py-3 pr-3">TOTAL</td>
                    <td className="py-3 px-3 text-right">{totals.members}</td>
                    <td className="py-3 px-3 text-right">{fmt(totals.form)}</td>
                    <td className="py-3 px-3 text-right">{fmt(totals.adjust)}</td>
                    <td className="py-3 px-3 text-right">{fmt(totals.ld)}</td>
                    <td className="py-3 pl-3 text-right text-gold-light print:text-black">{fmt(totals.total)}</td>
                  </tr>
                </tfoot>
              )}
            </table>
          </div>
        </>
      )}
    </div>
  );
}
