export default function StatusPill({ status }: { status: string }) {
  const map: Record<string, string> = {
    pending: "bg-yellow-500/15 text-yellow-400",
    approved: "bg-green-500/15 text-green-400",
    rejected: "bg-red-500/15 text-red-400",
  };
  return (
    <span className={`text-xs px-2 py-0.5 rounded-full ${map[status] || "bg-white/10 text-muted"}`}>
      {status}
    </span>
  );
}
