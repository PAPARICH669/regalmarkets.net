import { ReactNode } from "react";

export default function StatCard({
  label,
  value,
  icon,
  accent = false,
  sub,
}: {
  label: string;
  value: ReactNode;
  icon?: ReactNode;
  accent?: boolean;
  sub?: ReactNode;
}) {
  return (
    <div className="glass card-hover p-5 animate-fade-up">
      <div className="flex items-center justify-between">
        <p className="text-sm text-muted">{label}</p>
        {icon && (
          <span className={`grid place-items-center w-9 h-9 rounded-lg ${accent ? "gold-gradient text-black" : "bg-white/5 text-gold-light"}`}>
            {icon}
          </span>
        )}
      </div>
      <p className={`mt-3 text-2xl font-bold ${accent ? "gold-text" : ""}`}>{value}</p>
      {sub && <p className="mt-1 text-xs text-muted">{sub}</p>}
    </div>
  );
}
