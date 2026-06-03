import { create } from "zustand";
import api, { setToken } from "./api";

export interface AuthUser {
  id: number;
  username: string;
  email: string;
  rank: string;
  rank_level: number;
  referral_code: string;
  sponsor?: string | null;
  wallet_address?: string | null;
  is_admin: boolean;
  is_frozen: boolean;
  total_invested: number;
  total_fund: number;
  wallet_a: number;
  wallet_e: number;
  two_factor_enabled: boolean;
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
