"use client";

import { useCallback, useEffect, useState } from "react";
import { Search, Download, X } from "lucide-react";
import api, { apiError } from "@/lib/api";
import { usdt, shortDate } from "@/lib/format";

interface Row {
  id: number; username: string; name: string; is_dummy: boolean;
  invested: number; withdrawn: number; wallet_a: number; wallet_e: number;
}
interface Totals { count: number; invested: number; withdrawn: number; wallet_a: number; wallet_e: number; }

export default function AdminFinancials() {
  const [rows, setRows] = useState<Row[]>([]);
  const [totals, setTotals] = useState<Totals | null>(null);
  const [q, setQ] = useState("");
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState("");
  const [detailFor, setDetailFor] = useState<Row | null>(null);

  const load = useCallback((query: string) => {
    setLoading(true);
    api.get("/admin/member-financials", { params: { q: query } })
      .then((r) => { setRows(r.data.rows); setTotals(r.data.totals); })
      .catch((e) => setError(apiError(e)))
      .finally(() => setLoading(false));
  }, []);

  useEffect(() => { const t = setTimeout(() => load(q), 300); return () => clearTimeout(t); }, [q, load]);

  async function exportCsv() {
    try {
      const res = await api.get("/admin/member-financials/export", { params: { q }, responseType: "blob" });
      const url = window.URL.createObjectURL(new Blob([res.data]));
      const a = document.createElement("a");
      a.href = url; a.download = "regal_member_financials.csv"; a.click();
      window.URL.revokeObjectURL(url);
    } catch (e) { setError(apiError(e)); }
  }

  return (
    <div className="space-y-5">
      <div className="flex items-center justify-between flex-wrap gap-3">
        <div>
          <h1 className="text-2xl font-bold">Member Financials</h1>
          <p className="text-muted text-sm">Modal aktif, jumlah withdraw &amp; baki wallet setiap ahli.</p>
        </div>
        <button onClick={exportCsv} className="btn-ghost px-4 py-2 flex items-center gap-2"><Download size={16} /> Export CSV</button>
      </div>

      <div className="relative max-w-md">
        <Search size={16} className="absolute left-3 top-1/2 -translate-y-1/2 text-muted" />
        <input className="input-field pl-9" placeholder="Cari username / nama…" value={q} onChange={(e) => setQ(e.target.value)} />
      </div>

      {error && <div className="text-sm text-red-400 bg-red-500/10 border border-red-500/30 rounded-lg px-4 py-3">{error}</div>}

      <div className="glass overflow-x-auto">
        <table className="w-full text-sm">
          <thead className="bg-white/5 text-gold-light text-left">
            <tr>
              <th className="px-4 py-3">Ahli</th>
              <th className="text-right">Modal aktif</th>
              <th className="text-right">Total Withdraw</th>
              <th className="text-right">Baki A</th>
              <th className="text-right">Baki E</th>
            </tr>
          </thead>
          <tbody>
            {rows.map((r) => (
              <tr key={r.id} className="border-t border-[var(--line)] cursor-pointer hover:bg-white/5" onClick={() => setDetailFor(r)}>
                <td className="px-4 py-3">{r.name}{r.is_dummy && <span className="ml-2 text-[10px] text-orange-400">dummy</span>}<div className="text-xs text-muted">@{r.username}</div></td>
                <td className="text-right">{usdt(r.invested)}</td>
                <td className="text-right text-red-400">{usdt(r.withdrawn)}</td>
                <td className="text-right text-muted">{usdt(r.wallet_a)}</td>
                <td className="text-right text-muted">{usdt(r.wallet_e)}</td>
              </tr>
            ))}
            {loading && <tr><td colSpan={5} className="py-6 text-center text-muted">Loading…</td></tr>}
            {!loading && rows.length === 0 && <tr><td colSpan={5} className="py-6 text-center text-muted">Tiada ahli.</td></tr>}
          </tbody>
          {totals && rows.length > 0 && (
            <tfoot>
              <tr className="border-t-2 border-[var(--line)] font-bold">
                <td className="px-4 py-3">JUMLAH · {totals.count} ahli</td>
                <td className="text-right">{usdt(totals.invested)}</td>
                <td className="text-right text-red-400">{usdt(totals.withdrawn)}</td>
                <td className="text-right">{usdt(totals.wallet_a)}</td>
                <td className="text-right">{usdt(totals.wallet_e)}</td>
              </tr>
            </tfoot>
          )}
        </table>
      </div>

      {detailFor && <DetailModal row={detailFor} onClose={() => setDetailFor(null)} onChanged={() => load(q)} />}
    </div>
  );
}

interface Pkg { id: number; principal: string; total_return: string; total_paid: string; status: string; activated_at: string | null; }
interface Wd { id: number; amount: string; fee: string; net_amount: string; status: string; wallet_address: string | null; txid: string | null; created_at: string; processed_at: string | null; }

function DetailModal({ row, onClose, onChanged }: { row: Row; onClose: () => void; onChanged: () => void }) {
  const [packages, setPackages] = useState<Pkg[]>([]);
  const [withdrawals, setWithdrawals] = useState<Wd[]>([]);
  const [balA, setBalA] = useState(row.wallet_a);
  const [balE, setBalE] = useState(row.wallet_e);
  const [loading, setLoading] = useState(true);

  // Adjust form
  const [type, setType] = useState<"A" | "E" | "L">("A");
  const [direction, setDirection] = useState<"credit" | "debit">("credit");
  const [amount, setAmount] = useState("");
  const [note, setNote] = useState("");
  const [confirming, setConfirming] = useState(false);
  const [busy, setBusy] = useState(false);
  const [msg, setMsg] = useState(""); const [err, setErr] = useState("");

  const loadDetail = useCallback(() => {
    return api.get(`/admin/member-financials/${row.id}`).then((r) => {
      setPackages(r.data.packages); setWithdrawals(r.data.withdrawals);
      setBalA(Number(r.data.member.wallet_a)); setBalE(Number(r.data.member.wallet_e));
    }).finally(() => setLoading(false));
  }, [row.id]);
  useEffect(() => { loadDetail(); }, [loadDetail]);

  const curBal = type === "A" ? balA : type === "E" ? balE : 0;
  const newBal = curBal + (direction === "credit" ? 1 : -1) * (Number(amount) || 0);

  async function doAdjust() {
    setErr(""); setMsg(""); setBusy(true);
    try {
      await api.post(`/admin/members/${row.id}/adjust-wallet`, { type, direction, amount: Number(amount), note: note.trim() || undefined });
      setMsg(`Berjaya ${direction === "credit" ? "credit" : "debit"} ${usdt(amount)} ke ${type}-Wallet.`);
      setAmount(""); setNote(""); setConfirming(false);
      await loadDetail(); onChanged();
    } catch (e) { setErr(apiError(e)); } finally { setBusy(false); }
  }

  return (
    <div className="fixed inset-0 z-50 bg-black/70 flex items-center justify-center p-4" onClick={onClose}>
      <div className="w-full max-w-2xl max-h-[85vh] overflow-y-auto rounded-2xl border border-[var(--line)] bg-[var(--surface)]" onClick={(e) => e.stopPropagation()}>
        <div className="flex items-center gap-3 px-4 py-3 border-b border-[var(--line)] sticky top-0 bg-[var(--surface)] z-10">
          <div className="flex-1">
            <div className="font-semibold">{row.name}</div>
            <div className="text-xs text-muted">@{row.username}</div>
          </div>
          <button onClick={onClose} aria-label="Close" className="btn-ghost p-2"><X size={16} /></button>
        </div>

        <div className="p-4 grid grid-cols-3 gap-3">
          <Stat label="Modal aktif" value={usdt(row.invested)} />
          <Stat label="Baki A" value={usdt(balA)} />
          <Stat label="Baki E" value={usdt(balE)} />
        </div>

        {/* Adjust wallet */}
        <div className="mx-4 mb-4 p-4 rounded-xl border border-gold-light/30 bg-gold-light/5">
          <p className="text-sm font-semibold text-gold-light mb-3">Adjust wallet</p>
          {msg && <div className="mb-3 text-xs text-green-400 bg-green-500/10 border border-green-500/30 rounded-lg px-3 py-2">{msg}</div>}
          {err && <div className="mb-3 text-xs text-red-400 bg-red-500/10 border border-red-500/30 rounded-lg px-3 py-2">{err}</div>}
          <div className="grid grid-cols-2 gap-3">
            <div>
              <label className="text-xs text-muted">Wallet</label>
              <select className="input-field mt-1" value={type} onChange={(e) => { setType(e.target.value as "A" | "E" | "L"); setConfirming(false); }}>
                <option value="A">A-Wallet (modal)</option>
                <option value="E">E-Wallet (earning)</option>
                <option value="L">LD Wallet</option>
              </select>
            </div>
            <div>
              <label className="text-xs text-muted">Jenis</label>
              <select className="input-field mt-1" value={direction} onChange={(e) => { setDirection(e.target.value as "credit" | "debit"); setConfirming(false); }}>
                <option value="credit">Credit (+ tambah)</option>
                <option value="debit">Debit (− tolak)</option>
              </select>
            </div>
          </div>
          <div className="mt-3">
            <label className="text-xs text-muted">Jumlah (USDT)</label>
            <input type="number" step="0.01" min="0" className="input-field mt-1" value={amount} onChange={(e) => { setAmount(e.target.value); setConfirming(false); }} placeholder="0.00" />
          </div>
          <div className="mt-3">
            <label className="text-xs text-muted">Nota (sebab)</label>
            <input type="text" className="input-field mt-1" value={note} onChange={(e) => setNote(e.target.value)} placeholder="cth: pelarasan manual / bonus" maxLength={255} />
          </div>
          {Number(amount) > 0 && type !== "L" && (
            <div className="mt-3 text-xs bg-black/30 rounded-lg px-3 py-2">
              Baki {type}: <b>{usdt(curBal)}</b> → <b className={newBal < 0 ? "text-red-400" : "text-gold-light"}>{usdt(newBal)}</b> selepas {direction} {usdt(amount)}
              {newBal < 0 && <span className="text-red-400"> (baki tak cukup!)</span>}
            </div>
          )}
          {!confirming ? (
            <button disabled={!(Number(amount) > 0)} onClick={() => setConfirming(true)}
              className="btn-gold w-full py-2.5 text-sm mt-3 disabled:opacity-50">Adjust {direction === "credit" ? "credit" : "debit"} {amount ? usdt(amount) : ""}</button>
          ) : (
            <div className="mt-3 space-y-2">
              <p className="text-xs text-yellow-400">Sahkan: <b>{direction === "credit" ? "CREDIT" : "DEBIT"} {usdt(amount)}</b> ke <b>{type}-Wallet @{row.username}</b>?</p>
              <div className="flex gap-2">
                <button onClick={() => setConfirming(false)} className="btn-ghost flex-1 py-2 text-sm">Batal</button>
                <button disabled={busy} onClick={doAdjust} className="btn-gold flex-[2] py-2 text-sm">{busy ? "Memproses…" : "Ya, laksana"}</button>
              </div>
            </div>
          )}
        </div>

        <div className="px-4 pb-4">
          <h4 className="text-sm font-semibold text-gold-light mb-2">Pakej (modal)</h4>
          <div className="overflow-x-auto mb-5">
            <table className="w-full text-xs">
              <thead className="text-muted text-left"><tr><th className="py-1">#</th><th>Modal</th><th>Dibayar / Jumlah</th><th>Status</th><th>Tarikh</th></tr></thead>
              <tbody>
                {packages.map((p) => (
                  <tr key={p.id} className="border-t border-[var(--line)]">
                    <td className="py-1.5">#{p.id}</td>
                    <td>{usdt(p.principal)}</td>
                    <td>{usdt(p.total_paid)} / {usdt(p.total_return)}</td>
                    <td className="capitalize">{p.status}</td>
                    <td className="text-muted">{p.activated_at ? shortDate(p.activated_at) : "—"}</td>
                  </tr>
                ))}
                {!loading && packages.length === 0 && <tr><td colSpan={5} className="py-3 text-center text-muted">Tiada pakej.</td></tr>}
              </tbody>
            </table>
          </div>

          <h4 className="text-sm font-semibold text-gold-light mb-2">Withdraw</h4>
          <div className="overflow-x-auto">
            <table className="w-full text-xs">
              <thead className="text-muted text-left"><tr><th className="py-1">Jumlah</th><th>Fee</th><th>Net</th><th>Status</th><th>Tarikh</th></tr></thead>
              <tbody>
                {withdrawals.map((w) => (
                  <tr key={w.id} className="border-t border-[var(--line)]">
                    <td className="py-1.5">{usdt(w.amount)}</td>
                    <td>{usdt(w.fee)}</td>
                    <td>{usdt(w.net_amount)}</td>
                    <td className="capitalize">{w.status}</td>
                    <td className="text-muted">{shortDate(w.created_at)}</td>
                  </tr>
                ))}
                {!loading && withdrawals.length === 0 && <tr><td colSpan={5} className="py-3 text-center text-muted">Tiada withdraw.</td></tr>}
                {loading && <tr><td colSpan={5} className="py-3 text-center text-muted">Loading…</td></tr>}
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  );
}

function Stat({ label, value, danger }: { label: string; value: string; danger?: boolean }) {
  return (
    <div className="bg-black/20 rounded-lg p-3">
      <div className="text-xs text-muted">{label}</div>
      <div className={`text-base font-semibold ${danger ? "text-red-400" : "gold-text"}`}>{value}</div>
    </div>
  );
}
