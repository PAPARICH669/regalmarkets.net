import axios from "axios";

export const API_URL =
  process.env.NEXT_PUBLIC_API_URL || "http://localhost:8000/api";

export const TOKEN_KEY = "regal_token";

export function getToken(): string | null {
  if (typeof window === "undefined") return null;
  return window.localStorage.getItem(TOKEN_KEY);
}

export function setToken(token: string | null) {
  if (typeof window === "undefined") return;
  if (token) window.localStorage.setItem(TOKEN_KEY, token);
  else window.localStorage.removeItem(TOKEN_KEY);
}

const api = axios.create({
  baseURL: API_URL,
  headers: { Accept: "application/json" },
});

api.interceptors.request.use((config) => {
  const token = getToken();
  if (token) config.headers.Authorization = `Bearer ${token}`;
  return config;
});

api.interceptors.response.use(
  (res) => res,
  (error) => {
    const status = error?.response?.status;
    if (typeof window !== "undefined") {
      if (status === 503 && error.response?.data?.maintenance) {
        if (!window.location.pathname.startsWith("/maintenance")) {
          window.location.href = "/maintenance";
        }
      }
      if (status === 401 && !window.location.pathname.startsWith("/login")) {
        setToken(null);
      }
    }
    return Promise.reject(error);
  }
);

/** Pull a readable message out of an axios error (incl. Laravel validation). */
export function apiError(error: unknown, fallback = "Something went wrong."): string {
  const e = error as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } };
  const data = e?.response?.data;
  if (data?.errors) {
    const first = Object.values(data.errors)[0];
    if (first?.length) return first[0];
  }
  return data?.message || fallback;
}

export default api;
