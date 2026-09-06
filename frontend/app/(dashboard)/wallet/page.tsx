"use client";

import { useEffect, useState } from "react";
import { Wallet } from "lucide-react";
import api from "@/lib/api";
import { useAuth } from "@/lib/auth";
import { ChangeRequestForm, CRStatus, CR_FIELD, type ChangeReq } from "@/components/ChangeRequestForm";

const WALLET_FIELDS = ["wallet_address", "btc_address", "btc_native_address", "eth_address", "sol_address", "sol_native_address"];

export default function WalletPage() {
  const { user } = useAuth();
  const [requests, setRequests] = useState<ChangeReq[]>([]);
  const loadRequests = () => api.get("/account-changes").then((r) => setRequests(r.data)).catch(() => {});
  useEffect(() => { loadRequests(); }, []);

  const walletReqs = requests.filter((r) => WALLET_FIELDS.includes(r.field));

  return (
    <div className="space-y-6 max-w-2xl">
      <h1 className="text-2xl font-bold flex items-center gap-2"><Wallet size={22} className="text-gold-light" /> Wallet Addresses</h1>

      <div className="glass p-6">
        <p className="text-sm text-muted">
          Set the wallet address for each coin you want to withdraw to. First-time set is saved <b className="text-green-400">instantly</b>;
          changing an existing address needs a 6-digit code (TAC) to your <b>current email</b> + admin/staff approval.
        </p>
        <p className="text-xs text-muted mt-2">
          BTC &amp; SOL can be received on <b className="text-gold-light">BEP20 (0x…)</b> or their <b className="text-gold-light">native network</b> (Bitcoin / Solana).
          ETH &amp; USDT are BEP20.
        </p>

        <div className="mt-5 grid sm:grid-cols-2 gap-5">
          <ChangeRequestForm field="wallet_address" label="USDT Address (BEP20)" current={user?.wallet_address || "—"} placeholder="0x… (BEP20 / BSC)" onDone={loadRequests} />
          <ChangeRequestForm field="btc_address" label="BTC Address (BEP20)" current={user?.btc_address || "—"} placeholder="0x… (BEP20 / BSC)" onDone={loadRequests} />
          <ChangeRequestForm field="btc_native_address" label="BTC Address (Bitcoin network)" current={user?.btc_native_address || "—"} placeholder="bc1… / 1… / 3…" addrType="btc" onDone={loadRequests} />
          <ChangeRequestForm field="eth_address" label="ETH Address (BEP20)" current={user?.eth_address || "—"} placeholder="0x… (BEP20 / BSC)" onDone={loadRequests} />
          <ChangeRequestForm field="sol_address" label="SOL Address (BEP20)" current={user?.sol_address || "—"} placeholder="0x… (BEP20 / BSC)" onDone={loadRequests} />
          <ChangeRequestForm field="sol_native_address" label="SOL Address (Solana network)" current={user?.sol_native_address || "—"} placeholder="Base58 (Solana)" addrType="sol" onDone={loadRequests} />
        </div>

        {walletReqs.length > 0 && (
          <div className="mt-6 border-t border-[var(--line)] pt-4">
            <p className="text-sm font-medium mb-2">Recent wallet requests</p>
            <div className="space-y-2">
              {walletReqs.map((r) => (
                <div key={r.id} className="flex items-center justify-between gap-3 text-sm bg-black/30 rounded-lg px-3 py-2">
                  <span className="text-muted">{CR_FIELD[r.field] || r.field} → <span className="text-foreground break-all">{r.new_value}</span></span>
                  <CRStatus status={r.status} />
                </div>
              ))}
            </div>
          </div>
        )}
      </div>
    </div>
  );
}
