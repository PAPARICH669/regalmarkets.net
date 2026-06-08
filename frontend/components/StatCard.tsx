import { ReactNode } from "react";
import Link from "next/link";

export default function StatCard({
  label,
  value,
  icon,
  accent = false,
  sub,
  href,
}: {
  label: string;
  value: ReactNode;
  icon?: ReactNode;
  accent?: boolean;
  sub?: ReactNode;
  href?: string;
}) {
  const inner = (
    <>
      <div className="flex items-center justify-between">
        <p className="text-sm text-muted">{label}</p>
        {icon && (
          <span className={`grid place-items-center w-9 h-9 rounded-lg ${accent ? "gold-gradient text-black" : "bg-white/5 text-gold-light"}`}>
            {icon}
          </span>
        )}
      </div>
      <p className={`mt-3 text-2xl font-bold ${accent ? "gold-text" : ""}`}>{value}</p>
      {sub ? (
        <p className="mt-1 text-xs text-muted">{sub}</p>
      ) : href ? (
        <p className="mt-1 text-xs text-gold-light">View report →</p>
      ) : null}
    </>
  );

  if (href) {
    return (
      <Link href={href} className="glass card-hover p-5 animate-fade-up block hover:border-gold-light/40 transition">
        {inner}
      </Link>
    );
  }

  return <div className="glass card-hover p-5 animate-fade-up">{inner}</div>;
}
