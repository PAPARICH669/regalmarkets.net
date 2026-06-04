"use client";

import { useEffect, useState } from "react";
import { KeyRound, Wallet } from "lucide-react";
import api, { apiError } from "@/lib/api";
import { useAuth } from "@/lib/auth";

export default function ProfilePage() {
  const { user, refresh } = useAuth();

  const [pw, setPw] = useState({ current_password: "", password: "", password_confirmation: "" });
  const [pwMsg, setPwMsg] = useState(""); const [pwErr, setPwErr] = useState(""); const [pwLoading, setPwLoading] = useState(false);

  const [address, setAddress] = useState("");
  const [addrMsg, setAddrMsg] = useState(""); const [addrErr, setAddrErr] = useState(""); const [addrLoading, setAddrLoading] = useState(false);

  useEffect(() => { if (user?.wallet_address) setAddress(user.wallet_address); }, [user?.wallet_address]);

  async function changePassword(e: React.FormEvent) {
    e.preventDefault(); setPwMsg(""); setPwErr(""); setPwLoading(true);
    try {
      const { data } = await api.put("/profile/password", pw);
      setPwMsg(data.message);
      setPw({ current_password: "", password: "", password_confirmation: "" });
    } catch (err) { setPwErr(apiError(err)); } finally { setPwLoading(false); }
  }

  async function saveAddress(e: React.FormEvent) {
    e.preventDefault(); setAddrMsg(""); setAddrErr(""); setAddrLoading(true);
    try {
      const { data } = await api.put("/profile", { wallet_address: address });
      setAddrMsg(data.message); refresh();
    } catch (err) { setAddrErr(apiError(err)); } finally { setAddrLoading(false); }
  }

  return (
    <div className="space-y-6 max-w-xl">
      <h1 className="text-2xl font-bold">Profile & Security</h1>

      <div className="glass p-6">
        <h3 className="font-semibold flex items-center gap-2"><KeyRound size={18} className="text-gold-light" /> Change Password</h3>
        <p className="text-sm text-muted mt-1">Update your account password. You stay logged in here; other devices are signed out.</p>
        {pwMsg && <div className="mt-4 text-sm text-green-400 bg-green-500/10 border border-green-500/30 rounded-lg px-4 py-3">{pwMsg}</div>}
        {pwErr && <div className="mt-4 text-sm text-red-400 bg-red-500/10 border border-red-500/30 rounded-lg px-4 py-3">{pwErr}</div>}
        <form onSubmit={changePassword} className="mt-5 space-y-4">
          <div>
            <label className="text-sm text-muted">Current password</label>
            <input type="password" className="input-field mt-1" value={pw.current_password}
              onChange={(e) => setPw({ ...pw, current_password: e.target.value })} required />
          </div>
          <div className="grid sm:grid-cols-2 gap-3">
            <div>
              <label className="text-sm text-muted">New password</label>
              <input type="password" className="input-field mt-1" value={pw.password}
                onChange={(e) => setPw({ ...pw, password: e.target.value })} required minLength={6} />
            </div>
            <div>
              <label className="text-sm text-muted">Confirm new password</label>
              <input type="password" className="input-field mt-1" value={pw.password_confirmation}
                onChange={(e) => setPw({ ...pw, password_confirmation: e.target.value })} required minLength={6} />
            </div>
          </div>
          <button disabled={pwLoading} className="btn-gold px-6 py-2.5">{pwLoading ? "Saving…" : "Change Password"}</button>
        </form>
      </div>

      <div className="glass p-6">
        <h3 className="font-semibold flex items-center gap-2"><Wallet size={18} className="text-gold-light" /> USDT Withdrawal Address</h3>
        {addrMsg && <div className="mt-4 text-sm text-green-400 bg-green-500/10 border border-green-500/30 rounded-lg px-4 py-3">{addrMsg}</div>}
        {addrErr && <div className="mt-4 text-sm text-red-400 bg-red-500/10 border border-red-500/30 rounded-lg px-4 py-3">{addrErr}</div>}
        <form onSubmit={saveAddress} className="mt-4 space-y-4">
          <input className="input-field" placeholder="Your USDT wallet address" value={address}
            onChange={(e) => setAddress(e.target.value)} />
          <button disabled={addrLoading} className="btn-ghost px-6 py-2.5">{addrLoading ? "Saving…" : "Save Address"}</button>
        </form>
      </div>
    </div>
  );
}
