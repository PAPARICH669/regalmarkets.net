import { create } from "zustand";
import api, { setToken } from "./api";

export interface AuthUser {
  id: number;
  username: string;
  name: string;
  nickname?: string;
  email: string;
  phone?: string | null;
  country?: string | null;
  rank: string;
  rank_level: number;
  referral_code: string;
  sponsor?: string | null;
  wallet_address?: string | null;
  btc_address?: string | null;
  eth_address?: string | null;
  sol_address?: string | null;
  btc_native_address?: string | null;
  sol_native_address?: string | null;
  heir_name?: string | null;
  heir_phone?: string | null;
  id_type?: string | null;
  id_number?: string | null;
  kyc_status?: string;
  kyc_note?: string | null;
  email_verified?: boolean;
  is_admin: boolean;
  is_staff?: boolean;
  can_kyc?: boolean;
  can_cr?: boolean;
  is_ld?: boolean;
  is_frozen: boolean;
  total_invested: number;
  total_fund: number;
  wallet_a: number;
  wallet_e: number;
  wallet_l?: number;
  two_factor_enabled: boolean;
  created_at?: string | null;
}

interface AuthState {
  user: AuthUser | null;
  loading: boolean;
  hydrated: boolean;
  setAuth: (token: string, user: AuthUser) => void;
  refresh: () => Promise<void>;
  bootstrap: () => Promise<void>;
  logout: () => Promise<void>;
}

export const useAuth = create<AuthState>((set, get) => ({
  user: null,
  loading: false,
  hydrated: false,

  setAuth: (token, user) => {
    setToken(token);
    set({ user });
  },

  refresh: async () => {
    try {
      const { data } = await api.get("/me");
      set({ user: data });
    } catch {
      set({ user: null });
    }
  },

  bootstrap: async () => {
    if (get().hydrated) return;
    set({ loading: true });
    try {
      const { data } = await api.get("/me");
      set({ user: data });
    } catch {
      set({ user: null });
    } finally {
      set({ loading: false, hydrated: true });
    }
  },

  logout: async () => {
    try {
      await api.post("/logout");
    } catch {
      /* ignore */
    }
    setToken(null);
    set({ user: null });
  },
}));
