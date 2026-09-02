"use client";

import { useEffect, useMemo, useState } from "react";
import Link from "next/link";
import { Coins } from "lucide-react";
import api, { apiError } from "@/lib/api";
import { useAuth } from "@/lib/auth";
import { usdt, usdtFloor, floorTo, shortDate } from "@/lib/format";
import StatusPill from "@/components/StatusPill";
import KycBanner from "@/components/KycBanner";

interface Withdrawal {
  id: number; amount: string; net_amount: string; wallet_address: string;
  txid: string | null; status: string; created_at: string;
  coin?: string; network?: string; coin_amount_est?: string; coin_amount_actual?: string | null;
}
interface NetInfo { network: string; fee: number; type: string; address_key: string; }
interface CoinInfo { coin: string; name: string; dp: number; min: number; system_rate: number | null; available: boolean; networks: NetInfo[]; }
interface Cfg {
  min: number; max_amount: number; fee_flat: number; max_per_day: number;
  processing_hours: number; window_start: string; window_end: string;
  coin_swap_enabled: boolean; coins: CoinInfo[];
}

const COIN_STYLE: Record<string, string> = { USDT: "#26a17b", BTC: "#f7931a", ETH: "#627eea", SOL: "#9945ff" };
const ADDR_HINT: Record<string, string> = {
  evm: "BEP20 / EVM address (0x…, 42 characters).",
  btc: "Native Bitcoin address (bc1…, 1…, or 3…).",
  sol: "Native Solana address (base58).",
};

const fmtCoin = (n: number, dp: number) => n.toLocaleString("en-US", { minimumFractionDigits: dp, maximumFractionDigits: dp });
const trimCoin = (n: number, dp: number) => fmtCoin(n, dp).replace(/\.?0+$/, "");

export default function WithdrawPage() {
  const { user, refresh } = useAuth();
  const [amount, setAmount] = useState("");
  const [coin, setCoin] = useState("USDT");
  const [netIdx, setNetIdx] = useState(0);
  const [cfg, setCfg] = useState<Cfg | null>(null);
  const [coins, setCoins] = useState<CoinInfo[]>([]);
  const [msg, setMsg] = useState(""); const [error, setError] = useState("");
  const [loading, setLoading] = useState(false);
  const [history, setHistory] = useState<Withdrawal[]>([]);

  const load = () => api.get("/withdrawals").then((r) => setHistory(r.data.data));
  useEffect(() => {
    load();
    api.get("/withdrawals/config").then((r) => { setCfg(r.data); setCoins(r.data.coins || []); });
  }, []);
  useEffect(() => {
    if (coin === "USDT") return;
    const id = setInterval(() => api.get("/withdrawals/coin-rates").then((r) => setCoins(r.data.coins || [])).catch(() => {}), 60000);
    return () => clearInterval(id);
  }, [coin]);

  const sel = useMemo(() => coins.find((c) => c.coin === coin) || null, [coins, coin]);
  const selNet = sel?.networks?.[netIdx] || sel?.networks?.[0] || null;
  const isUsdt = coin === "USDT";
  const rate = sel?.system_rate ?? 1;
  const rateUnavailable = !isUsdt && (!sel || sel.system_rate == null);

  function pickCoin(c: string) { setCoin(c); setNetIdx(0); }

  const address = useMemo(() => {
    if (!selNet) return "";
    return ((user as unknown as Record<string, string | null>)?.[selNet.address_key] || "").trim();
  }, [user, selNet]);
  const hasAddress = address.length > 0;

  const amt = Number(amount) || 0;

  const est = useMemo(() => {
    if (!cfg || !sel || !selNet) return null;
    if (isUsdt) {
      const fee = cfg.fee_flat;
      return { receive: Math.max(amt - fee, 0), unit: "USDT", dp: 2, feeText: usdt(fee), minUsdt: cfg.min };
    }
    if (sel.system_rate == null) return null;
    const gross = rate > 0 ? amt / rate : 0;
    const receive = Math.max(gross - selNet.fee, 0);
    const minUsdt = Math.max(cfg.min, (sel.min + selNet.fee) * rate);
    return { receive, unit: sel.coin, dp: sel.dp, feeText: `${trimCoin(selNet.fee, sel.dp)} ${sel.coin}`, gross, minUsdt };
  }, [cfg, sel, selNet, amt, rate, isUsdt]);

  const belowMin = !!est && amt > 0 && amt < est.minUsdt;

  async function submit(e: React.FormEvent) {
    e.preventDefault(); setMsg(""); setError(""); setLoading(true);
    try {
      const { data } = await api.post("/withdrawals", { amount, coin, network: selNet?.network });
      setMsg(data.message); setAmount(""); load(); refresh();
    } catch (err) { setError(apiError(err)); } finally { setLoading(false); }
  }

  const swapEnabled = cfg?.coin_swap_enabled ?? false;
  const shownCoins = swapEnabled ? coins : coins.filter((c) => c.coin === "USDT");
  const disabled = loading || !hasAddress || belowMin || rateUnavailable || amt <= 0;

  return (
    <div className="space-y-6">
      <h1 className="text-2xl font-bold">Withdraw USDT</h1>
      <KycBanner action="withdraw" />
      <div className="grid lg:grid-cols-2 gap-6">
        <div className="glass p-6">
          <h3 className="font-semibold">Request Withdrawal</h3>
          <p className="text-sm text-muted mt-1">
            From E-WALLET. Min {cfg ? usdt(cfg.min) : "…"}, max {cfg ? usdt(cfg.max_amount) : "…"} per withdrawal.
            <b className="text-foreground"> {cfg?.max_per_day ?? 1} withdrawal/day.</b>
            {" "}Processed within {cfg?.processing_hours ?? 72} working hours (Mon–Fri).
          </p>
          {cfg && (
            <div className="mt-3 text-sm text-gold-light bg-gold-light/5 border border-gold-light/20 rounded-lg px-4 py-2">
              ⏰ Requests are accepted only between <b>{cfg.window_start}–{cfg.window_end}</b>.
            </div>
          )}
          <div className="mt-4 flex items-center justify-between bg-black/30 rounded-lg p-3">
            <span className="text-sm text-muted flex items-center gap-2"><Coins size={16} className="text-gold-light" /> E-WALLET balance</span>
            <span className="text-lg font-bold gold-text">{usdt(user?.wallet_e ?? 0)}</span>
          </div>
          {msg && <div className="mt-4 text-sm text-green-400 bg-green-500/10 border border-green-500/30 rounded-lg px-4 py-3">{msg}</div>}
          {error && <div className="mt-4 text-sm text-red-400 bg-red-500/10 border border-red-500/30 rounded-lg px-4 py-3">{error}</div>}

          <form onSubmit={submit} className="mt-5 space-y-4">
            {shownCoins.length > 1 && (
              <div>
                <label className="text-sm text-muted">Receive in</label>
                <div className="mt-2 grid grid-cols-4 gap-2">
                  {shownCoins.map((c) => {
                    const on = c.coin === coin;
                    const off = c.coin !== "USDT" && !c.available;
                    return (
                      <button type="button" key={c.coin} disabled={off} onClick={() => pickCoin(c.coin)}
                        className={`rounded-xl border px-2 py-2 text-center transition ${on ? "border-gold-light bg-gold-light/10" : "border-[var(--line)] bg-black/20 hover:border-gold-light/40"} ${off ? "opacity-40 cursor-not-allowed" : ""}`}>
                        <span className="mx-auto mb-1 flex h-6 w-6 items-center justify-center rounded-full text-[11px] font-bold text-white" style={{ background: COIN_STYLE[c.coin] || "#555" }}>{c.coin[0]}</span>
                        <span className="block text-xs font-semibold">{c.coin}</span>
                      </button>
                    );
                  })}
                </div>
              </div>
            )}

            {/* Network selector — only when the coin has more than one network */}
            {sel && sel.networks.length > 1 && (
              <div>
                <label className="text-sm text-muted">Network</label>
                <div className="mt-2 flex flex-wrap gap-2">
                  {sel.networks.map((n, i) => {
                    const on = i === netIdx;
                    return (
                      <button type="button" key={n.network} onClick={() => setNetIdx(i)}
                        className={`rounded-lg border px-3 py-1.5 text-left transition ${on ? "border-gold-light bg-gold-light/10 text-gold-light" : "border-[var(--line)] bg-black/20 text-muted hover:border-gold-light/40"}`}>
                        <span className="block text-sm font-medium">{n.network}</span>
                        <span className="block text-[10px] opacity-80">fee {trimCoin(n.fee, sel.dp)} {sel.coin}</span>
                      </button>
                    );
                  })}
                </div>
              </div>
            )}

            <div>
              <div className="flex items-center justify-between">
                <label className="text-sm text-muted">Amount (USDT)</label>
                <button type="button" onClick={() => setAmount(String(floorTo(user?.wallet_e ?? 0)))}
                  className="text-xs px-2 py-0.5 rounded bg-gold-light/15 text-gold-light hover:bg-gold-light/25">
                  Max {usdtFloor(user?.wallet_e ?? 0)}
                </button>
              </div>
              <input type="number" step="0.01" className="input-field mt-1" value={amount} onChange={(e) => setAmount(e.target.value)} required />
            </div>

            {amt > 0 && est && !rateUnavailable && (
              <div className={`rounded-lg p-3 space-y-1 text-sm ${belowMin ? "bg-red-500/5 border border-red-500/30" : "bg-black/30"}`}>
                <div className="flex justify-between font-semibold">
                  <span>{isUsdt ? "You receive" : "Estimated net received"}</span>
                  <span className="gold-text">{fmtCoin(est.receive, est.dp)} {est.unit}</span>
                </div>
                {!isUsdt && <div className="flex justify-between text-muted"><span>System rate</span><span>1 {coin} ≈ {fmtCoin(rate, rate < 10 ? 2 : 0)} USDT</span></div>}
                <div className="flex justify-between text-muted"><span>Network fee</span><span>{est.feeText}</span></div>
                {!isUsdt && <div className="flex justify-between text-muted"><span>Network</span><span>{selNet?.network}</span></div>}
                {belowMin && <div className="text-red-400 text-xs pt-1">Minimum for {coin}{!isUsdt && selNet ? ` (${selNet.network})` : ""} is {usdt(est.minUsdt)}.</div>}
                {!isUsdt && <div className="text-[11px] text-muted pt-1">Estimate · final amount follows the rate at the time it is processed.</div>}
              </div>
            )}
            {rateUnavailable && (
              <div className="text-sm text-yellow-400 bg-yellow-500/10 border border-yellow-500/30 rounded-lg px-4 py-3">
                Live price for {coin} is temporarily unavailable. Try again shortly or withdraw in USDT.
              </div>
            )}

            <div>
              <label className="text-sm text-muted">
                {coin} address{!isUsdt && selNet ? ` · ${selNet.network}` : " (BEP20)"} <span className="text-xs">🔒 set in Profile</span>
              </label>
              <input className="input-field mt-1 opacity-70" value={hasAddress ? address : ""} placeholder={`No ${coin}${selNet ? " " + selNet.network : ""} address set`} disabled />
              {hasAddress ? (
                <p className="text-xs text-muted mt-1">🔒 Locked. Change it in your <Link href="/profile" className="text-gold-light underline">Profile</Link> (needs admin approval).</p>
              ) : (
                <p className="text-xs text-red-400 mt-1">⚠️ No {coin}{selNet ? " " + selNet.network : ""} address set. Add it in your <Link href="/profile" className="text-gold-light underline">Profile</Link>. {selNet ? ADDR_HINT[selNet.type] : ""}</p>
              )}
            </div>

            <button disabled={disabled} className="btn-gold w-full py-2.5 disabled:opacity-50">{loading ? "Requesting…" : "Request Withdrawal"}</button>
          </form>
        </div>

        <div className="glass p-6">
          <h3 className="font-semibold mb-4">Withdrawal History</h3>
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead className="text-gold-light text-left"><tr><th className="py-2">Amount</th><th>Receive</th><th>Status</th><th>Date</th></tr></thead>
              <tbody>
                {history.map((w) => (
                  <tr key={w.id} className="border-t border-[var(--line)]">
                    <td className="py-2">{usdt(w.amount)}</td>
                    <td>
                      {!w.coin || w.coin === "USDT"
                        ? usdt(w.net_amount)
                        : <span>{trimCoin(Number(w.coin_amount_actual ?? w.coin_amount_est ?? 0), 8)} {w.coin}<span className="text-xs text-muted"> {w.network}{w.coin_amount_actual ? "" : " · est"}</span></span>}
                    </td>
                    <td><StatusPill status={w.status} /></td>
                    <td className="text-muted text-xs">{shortDate(w.created_at)}</td>
                  </tr>
                ))}
                {history.length === 0 && <tr><td colSpan={4} className="py-4 text-muted text-center">No withdrawals yet.</td></tr>}
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  );
}
