import { Crown } from "lucide-react";
import { RANK_COLORS } from "@/lib/format";

export default function RankBadge({ rank }: { rank: string }) {
  const color = RANK_COLORS[rank] || "#9b9bac";
  return (
    <span
      className="rank-badge"
      style={{ color, borderColor: `${color}66`, background: `${color}1a` }}
    >
      <Crown size={13} />
      {rank}
    </span>
  );
}
