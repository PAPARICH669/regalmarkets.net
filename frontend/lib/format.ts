export function usdt(value: number | string | undefined | null, dp = 2): string {
  const n = Number(value ?? 0);
  return n.toLocaleString("en-US", { minimumFractionDigits: dp, maximumFractionDigits: dp }) + " USDT";
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
