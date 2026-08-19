export function usdt(value: number | string | undefined | null, dp = 2): string {
  const n = Number(value ?? 0);
  return n.toLocaleString("en-US", { minimumFractionDigits: dp, maximumFractionDigits: dp }) + " USDT";
}

// Floor (round DOWN) to `dp` decimals. Balances must never display more than
// the member actually holds — rounding up (14.1985 -> 14.20) lets them request
// a figure the wallet can't cover, which the server then rejects.
export function floorTo(value: number | string | undefined | null, dp = 2): number {
  const n = Number(value ?? 0);
  const f = Math.pow(10, dp);
  return Math.floor(n * f) / f;
}

export function usdtFloor(value: number | string | undefined | null, dp = 2): string {
  return usdt(floorTo(value, dp), dp);
}

export function num(value: number | string | undefined | null, dp = 2): string {
  const n = Number(value ?? 0);
  return n.toLocaleString("en-US", { minimumFractionDigits: dp, maximumFractionDigits: dp });
}

export function shortDate(value: string | null | undefined): string {
  if (!value) return "—";
  const d = new Date(value);
  return d.toLocaleString("en-MY", { timeZone: "Asia/Kuala_Lumpur", dateStyle: "medium", timeStyle: "short" });
}

export const RANK_COLORS: Record<string, string> = {
  USER: "#9b9bac",
  FAN: "#60a5fa",
  SENIOR: "#a78bfa",
  "TEAM LEADER": "#f59e0b",
  "GROUP LEADER": "#e7c873",
};
