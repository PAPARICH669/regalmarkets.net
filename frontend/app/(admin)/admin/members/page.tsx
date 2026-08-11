"use client";

import { useCallback, useEffect, useState } from "react";
import api, { apiError } from "@/lib/api";
import { usdt } from "@/lib/format";
import { useAuth } from "@/lib/auth";
import RankBadge from "@/components/RankBadge";

interface Member {
  id: number; username: string; email: string; phone?: string; kyc_status?: string;
  email_verified_at?: string | null;
  total_fund: string; total_invested: string; wallet_address?: string | null;
  is_frozen: boolean; is_staff?: boolean; is_admin?: boolean; is_ld?: boolean; can_kyc?: boolean; can_cr?: boolean; referrals_count: number;
  rank?: { id: number; name: string }; sponsor?: { id: number; username: string } | null;
}
interface Rank { id: number; name: string; }

export default function AdminMembers() {
  const me = useAuth((s) => s.user);
  const isAdmin = !!me?.is_admin;
  const [items, setItems] = useState<Member[]>([]);
  const [ranks, setRanks] = useState<Rank[]>([]);
  const [search, setSearch] = useState("");
  const [error, setError] = useState("");

  const load = useCallback(() => {
    api.get(`/admin/members${search ? `?search=${encodeURIComponent(search)}` : ""}`).then((r) => setItems(r.data.data));
  }, [search]);
  useEffect(() => { load(); if (isAdmin) api.get("/ranks").then((r) => setRanks(r.data)); }, [load, isAdmin]);

  async function freeze(id: number) {
    try { await api.post(`/admin/members/${id}/freeze`); load(); } catch (e) { setError(apiError(e)); }
  }
  async function verifyEmail(id: number, username: string) {
    if (!window.confirm(`Mark @${username}'s email as verified? They will be able to log in.`)) return;
    try { await api.post(`/admin/members/${id}/verify-email`); load(); } catch (e) { setError(apiError(e)); }
  }
  async function adjust(id: number) {
    const type = window.prompt("Wallet — A (capital), E (earnings), or L (LD WALLET):", "A"); if (!type) return;
    const direction = window.prompt("credit or debit:", "credit"); if (!direction) return;
    const amount = window.prompt("Amount:"); if (!amount) return;
    try { await api.post(`/admin/members/${id}/adjust-wallet`, { type: type.trim().toUpperCase(), direction, amount }); load(); }
    catch (e) { setError(apiError(e)); }
  }
  async function setRank(id: number, rank_id: number) {
    try { await api.post(`/admin/members/${id}/rank`, { rank_id }); load(); } catch (e) { setError(apiError(e)); }
  }
  async function editContact(id: number, email: string, phone: string) {
    const newEmail = window.prompt("Email:", email); if (newEmail === null) return;
    const newPhone = window.prompt("Phone:", phone || "") ?? "";
    try { await api.post(`/admin/members/${id}/contact`, { email: newEmail, phone: newPhone }); load(); }
    catch (e) { setError(apiError(e)); }
  }
  async function toggleCanKyc(id: number) {
    try { await api.post(`/admin/members/${id}/can-kyc`); load(); } catch (e) { setError(apiError(e)); }
  }
  async function toggleCanCr(id: number) {
    try { await api.post(`/admin/members/${id}/can-cr`); load(); } catch (e) { setError(apiError(e)); }
  }
  async function toggleLd(id: number) {
    try { await api.post(`/admin/members/${id}/ld`); load(); } catch (e) { setError(apiError(e)); }
  }
  async function setWallet(id: number, current?: string | null) {
    const addr = window.prompt("USDT withdrawal address (BEP20). Leave blank to clear:", current || "");
    if (addr === null) return;
    try { await api.post(`/admin/members/${id}/wallet-address`, { wallet_address: addr.trim() || null }); load(); }
    catch (e) { setError(apiError(e)); }
  }
  async function resetPw(id: number, username: string) {
    const pwd = window.prompt(`New password for ${username} (min 6, leave blank to auto-generate):`);
    if (pwd === null) return;
    try {
      const { data } = await api.post(`/admin/members/${id}/reset-password`, pwd ? { password: pwd } : {});
      window.alert(`Password reset for ${username}.\nNew password: ${data.new_password}\n\nShare it with the member securely.`);
    } catch (e) { setError(apiError(e)); }
  }

  return (
    <div className="space-y-5">
      <div className="flex items-center justify-between flex-wrap gap-3">
        <h1 className="text-2xl font-bold">Members</h1>
        <input className="input-field w-64" placeholder="Search username/email/phone…" value={search} onChange={(e) => setSearch(e.target.value)} />
      </div>
      {error && <div className="text-sm text-red-400 bg-red-500/10 border border-red-500/30 rounded-lg px-4 py-3">{error}</div>}
      <div className="glass overflow-x-auto">
        <table className="w-full text-sm">
          <thead className="bg-white/5 text-gold-light text-left"><tr><th className="px-4 py-3">Member</th><th>Rank</th><th>KYC</th><th>Fund</th><th>Invested</th><th>Directs</th><th>Status</th><th>Actions</th></tr></thead>
          <tbody>
            {items.map((m) => (
              <tr key={m.id} className="border-t border-[var(--line)]">
                <td className="px-4 py-3">
                  {m.username}
                  {m.is_admin && <span className="ml-2 text-[10px] px-1.5 py-0.5 rounded bg-gold-light/15 text-gold-light">ADMIN</span>}
                  {m.can_kyc && <span className="ml-2 text-[10px] px-1.5 py-0.5 rounded bg-blue-500/15 text-blue-300">KYC</span>}
                  {m.can_cr && <span className="ml-2 text-[10px] px-1.5 py-0.5 rounded bg-purple-500/15 text-purple-300">CR</span>}
                  {m.is_ld && <span className="ml-2 text-[10px] px-1.5 py-0.5 rounded bg-gold-light/15 text-gold-light">LD</span>}
                  <div className="text-xs text-muted">{m.email}</div>
                  <div className="text-xs text-muted">📞 {m.phone || "—"}</div>
                  <div className="text-xs text-muted">👤 Sponsor: {m.sponsor ? `@${m.sponsor.username}` : "—"}</div>
                  <div className="text-xs text-muted truncate max-w-[200px]" title={m.wallet_address || ""}>💳 {m.wallet_address || "no address"}</div>
                </td>
                <td>{m.rank ? <RankBadge rank={m.rank.name} /> : "—"}</td>
                <td><KycPill status={m.kyc_status} /></td>
                <td>{usdt(m.total_fund)}</td>
                <td>{usdt(m.total_invested)}</td>
                <td>{m.referrals_count}</td>
                <td>{m.is_frozen ? <span className="text-xs text-red-400">Frozen</span> : <span className="text-xs text-green-400">Active</span>}</td>
                <td>
                  <div className="flex flex-wrap gap-1.5">
                    <button onClick={() => editContact(m.id, m.email, m.phone || "")} className="btn-ghost px-2 py-1 text-xs">Contact</button>
                    {isAdmin && (<>
                      {!m.email_verified_at && (
                      <button onClick={() => verifyEmail(m.id, m.username)} className="px-2 py-1 text-xs rounded bg-green-500/20 text-green-300" title="Email belum verify — tekan untuk sahkan manual">✓ Verify Email</button>
                    )}
                    <button onClick={() => freeze(m.id)} className="btn-ghost px-2 py-1 text-xs">{m.is_frozen ? "Unfreeze" : "Freeze"}</button>
                      <button onClick={() => adjust(m.id)} className="btn-ghost px-2 py-1 text-xs">Adjust</button>
                      <button onClick={() => resetPw(m.id, m.username)} className="btn-ghost px-2 py-1 text-xs">Reset PW</button>
                      <button onClick={() => setWallet(m.id, m.wallet_address)} className="btn-ghost px-2 py-1 text-xs">Wallet</button>
                      {!m.is_admin && (
                        <button onClick={() => toggleCanKyc(m.id)} className={`px-2 py-1 text-xs rounded ${m.can_kyc ? "bg-blue-500/20 text-blue-300" : "btn-ghost"}`}>
                          {m.can_kyc ? "✓ KYC" : "KYC"}
                        </button>
                      )}
                      {!m.is_admin && (
                        <button onClick={() => toggleCanCr(m.id)} className={`px-2 py-1 text-xs rounded ${m.can_cr ? "bg-purple-500/20 text-purple-300" : "btn-ghost"}`}>
                          {m.can_cr ? "✓ Change Req" : "Change Req"}
                        </button>
                      )}
                      {!m.is_admin && (
                        <button onClick={() => toggleLd(m.id)} className={`px-2 py-1 text-xs rounded ${m.is_ld ? "bg-gold-light/20 text-gold-light" : "btn-ghost"}`}>
                          {m.is_ld ? "✓ LD" : "LD"}
                        </button>
                      )}
                      <select className="bg-[var(--surface)] border border-[var(--line)] rounded px-1 py-1 text-xs"
                        value={m.rank?.id ?? ""} onChange={(e) => setRank(m.id, Number(e.target.value))}>
                        {ranks.map((r) => <option key={r.id} value={r.id}>{r.name}</option>)}
                      </select>
                    </>)}
                  </div>
                </td>
              </tr>
            ))}
            {items.length === 0 && <tr><td colSpan={8} className="py-6 text-center text-muted">No members.</td></tr>}
          </tbody>
        </table>
      </div>
    </div>
  );
}

function KycPill({ status }: { status?: string }) {
  const s = status || "unsubmitted";
  const map: Record<string, string> = {
    unsubmitted: "bg-white/10 text-muted",
    pending: "bg-yellow-500/15 text-yellow-400",
    verified: "bg-green-500/15 text-green-400",
    rejected: "bg-red-500/15 text-red-400",
  };
  return <span className={`text-xs px-2 py-0.5 rounded-full capitalize ${map[s]}`}>{s}</span>;
}
