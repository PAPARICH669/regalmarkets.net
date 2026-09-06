"use client";

import { useState } from "react";
import api, { apiError } from "@/lib/api";
import { useAuth } from "@/lib/auth";

export interface ChangeReq { id: number; field: string; new_value: string; status: string; }

export const CR_FIELD: Record<string, string> = {
  email: "Email", phone: "Phone", wallet_address: "USDT",
  btc_address: "BTC (BEP20)", eth_address: "ETH", sol_address: "SOL (BEP20)",
  btc_native_address: "BTC (Bitcoin)", sol_native_address: "SOL (Solana)",
};

export function CRStatus({ status }: { status: string }) {
  const map: Record<string, string> = {
    pending_tac: "bg-white/10 text-muted",
    pending: "bg-yellow-500/15 text-yellow-400",
    approved: "bg-green-500/15 text-green-400",
    rejected: "bg-red-500/15 text-red-400",
  };
  const label: Record<string, string> = {
    pending_tac: "Awaiting code",
    pending: "Pending approval",
    approved: "Approved",
    rejected: "Rejected",
  };
  return <span className={`shrink-0 text-xs px-2 py-0.5 rounded-full ${map[status] || "bg-white/10 text-muted"}`}>{label[status] || status}</span>;
}

export function ChangeRequestForm({
  field, label, current, placeholder, inputType = "text", addrType = "evm", onDone,
}: {
  field: "email" | "wallet_address" | "phone" | "btc_address" | "eth_address" | "sol_address" | "btc_native_address" | "sol_native_address";
  label: string;
  current: string;
  placeholder: string;
  inputType?: string;
  addrType?: "evm" | "btc" | "sol";
  onDone?: () => void;
}) {
  const isAddress = field !== "email" && field !== "phone";
  const [step, setStep] = useState<"idle" | "tac">("idle");
  const [newValue, setNewValue] = useState("");
  const [reqId, setReqId] = useState<number | null>(null);
  const [code, setCode] = useState("");
  const [msg, setMsg] = useState(""); const [err, setErr] = useState(""); const [loading, setLoading] = useState(false);
  const refresh = useAuth((s) => s.refresh);
  const isFirstTime = isAddress && (!current || current === "—");

  async function submitNew(e: React.FormEvent) {
    e.preventDefault(); setMsg(""); setErr(""); setLoading(true);
    try {
      const { data } = await api.post("/account-changes", { field, new_value: newValue });
      if (data.applied) {
        setMsg(data.message); setNewValue("");
        await refresh?.(); onDone?.();
        return;
      }
      setReqId(data.request_id); setStep("tac"); setMsg(data.message);
    } catch (e) { setErr(apiError(e)); } finally { setLoading(false); }
  }
  async function confirmTac(e: React.FormEvent) {
    e.preventDefault(); setMsg(""); setErr(""); setLoading(true);
    try {
      const { data } = await api.post("/account-changes/verify-tac", { request_id: reqId, code });
      setMsg(data.message); setStep("idle"); setNewValue(""); setCode(""); setReqId(null);
      onDone?.();
    } catch (e) { setErr(apiError(e)); } finally { setLoading(false); }
  }
  async function resend() {
    setErr(""); try { const { data } = await api.post("/account-changes/resend-tac", { request_id: reqId }); setMsg(data.message); } catch (e) { setErr(apiError(e)); }
  }

  // Live format check, per network type (evm = 0x, btc = native, sol = base58).
  const walletCheck = (() => {
    if (!isAddress) return null;
    const v = newValue.trim();
    if (v === "") return null;
    if (addrType === "btc") {
      if (/^bc1[a-z0-9]{25,59}$/.test(v) || /^[13][a-km-zA-HJ-NP-Z1-9]{25,34}$/.test(v)) return { ok: true, typing: false, msg: "✓ Valid Bitcoin address." };
      if (/^(bc1|[13])/.test(v)) return { ok: false, typing: true, msg: "Keep typing… Bitcoin address (bc1…/1…/3…)." };
      return { ok: false, typing: false, msg: "Must be a Bitcoin address (bc1…, 1…, or 3…)." };
    }
    if (addrType === "sol") {
      if (/^[1-9A-HJ-NP-Za-km-z]{32,44}$/.test(v)) return { ok: true, typing: false, msg: "✓ Valid Solana address." };
      if (v.length < 32) return { ok: false, typing: true, msg: `Keep typing… Solana base58 (32–44). Now ${v.length}.` };
      return { ok: false, typing: false, msg: "Must be a valid Solana base58 address (32–44 chars)." };
    }
    // evm
    if (!/^0x/i.test(v)) return { ok: false, typing: false, msg: "Must start with 0x." };
    if (/[^0-9a-fA-F]/.test(v.slice(2))) return { ok: false, typing: false, msg: "Invalid characters — only 0-9 and a-f allowed." };
    if (v.length < 42) return { ok: false, typing: true, msg: `Too short — need 42 characters (0x + 40). Now ${v.length}.` };
    if (v.length > 42) return { ok: false, typing: false, msg: `Too long — need 42 characters. Now ${v.length}.` };
    return { ok: true, typing: false, msg: "✓ Valid BEP20 (BSC) address format." };
  })();
  const walletBlocks = isAddress && !!newValue.trim() && !!walletCheck && !walletCheck.ok;

  return (
    <div className="bg-black/20 rounded-xl p-4 border border-[var(--line)]">
      <label className="text-sm font-medium">{label}</label>
      <p className="text-xs text-muted mt-0.5 break-all">Current: {current}</p>
      {isAddress && (
        <p className={`text-[11px] mb-2 ${isFirstTime ? "text-green-400" : "text-yellow-400"}`}>
          {isFirstTime ? "First-time — saved instantly, no approval needed." : "Changing needs admin approval (security)."}
        </p>
      )}
      {field === "email" && <div className="mb-1" />}
      {msg && <div className="mb-2 text-xs text-green-400 bg-green-500/10 border border-green-500/30 rounded-lg px-3 py-2">{msg}</div>}
      {err && <div className="mb-2 text-xs text-red-400 bg-red-500/10 border border-red-500/30 rounded-lg px-3 py-2">{err}</div>}

      {step === "idle" ? (
        <form onSubmit={submitNew} className="space-y-2">
          <input type={inputType} className="input-field" style={walletCheck ? { borderColor: walletCheck.ok ? "#4ade80" : walletCheck.typing ? "#facc15" : "#f87171" } : undefined}
            placeholder={placeholder} value={newValue} onChange={(e) => setNewValue(e.target.value)} required />
          {walletCheck && (
            <p className={`text-[11px] flex items-start gap-1 ${walletCheck.ok ? "text-green-400" : walletCheck.typing ? "text-yellow-400" : "text-red-400"}`}>
              <span>{walletCheck.ok ? "✓" : walletCheck.typing ? "…" : "⚠"}</span><span>{walletCheck.msg}</span>
            </p>
          )}
          <button disabled={loading || !newValue || walletBlocks} className="btn-gold w-full py-2 text-sm disabled:opacity-50">{loading ? "Saving…" : (isFirstTime ? "Save address" : "Request change → send code")}</button>
        </form>
      ) : (
        <form onSubmit={confirmTac} className="space-y-2">
          <input inputMode="numeric" maxLength={6} className="input-field text-center tracking-[0.4em] font-mono" placeholder="······" value={code} onChange={(e) => setCode(e.target.value.replace(/\D/g, ""))} required />
          <div className="flex gap-2">
            <button disabled={loading || code.length < 6} className="btn-gold flex-1 py-2 text-sm disabled:opacity-50">{loading ? "Confirming…" : "Confirm"}</button>
            <button type="button" onClick={resend} className="btn-ghost px-3 py-2 text-xs">Resend</button>
            <button type="button" onClick={() => { setStep("idle"); setCode(""); setMsg(""); setErr(""); }} className="btn-ghost px-3 py-2 text-xs">Cancel</button>
          </div>
        </form>
      )}
    </div>
  );
}
