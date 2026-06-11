"use client";

import { useState } from "react";
import { ChevronDown, ChevronRight, User as UserIcon } from "lucide-react";
import { RANK_COLORS, usdt } from "@/lib/format";

export interface TreeNode {
  id: number;
  username: string;
  rank: string;
  total_fund: number;
  total_invested: number;
  depth: number;
  children: TreeNode[];
}

// Rank name -> logo file in /public/ranks (user/fan/senior/team-leader/group-leader .jpg).
function rankSlug(rank?: string) {
  return (rank || "USER").toLowerCase().replace(/\s+/g, "-");
}

function Node({ node, root = false }: { node: TreeNode; root?: boolean }) {
  const [open, setOpen] = useState(root || node.depth < 1);
  const [imgOk, setImgOk] = useState(true);
  const color = RANK_COLORS[node.rank] || "#9b9bac";
  const hasChildren = node.children?.length > 0;
  const logo = `/ranks/${rankSlug(node.rank)}.jpg`;

  return (
    <div className="ml-2">
      <div className="flex items-center gap-2 py-1.5">
        <button
          onClick={() => setOpen((o) => !o)}
          className={`w-5 h-5 grid place-items-center rounded ${hasChildren ? "text-gold-light" : "opacity-0"}`}
        >
          {hasChildren ? (open ? <ChevronDown size={15} /> : <ChevronRight size={15} />) : null}
        </button>
        {imgOk ? (
          // Rank logo — changes automatically with the member's rank.
          // eslint-disable-next-line @next/next/no-img-element
          <img
            src={logo}
            alt={`${node.rank} rank`}
            onError={() => setImgOk(false)}
            className="w-7 h-7 rounded-full object-cover border shrink-0"
            style={{ borderColor: `${color}66` }}
          />
        ) : (
          <span
            className="grid place-items-center w-7 h-7 rounded-full text-black text-xs shrink-0"
            style={{ background: color }}
          >
            <UserIcon size={14} />
          </span>
        )}
        <span className="font-medium">{node.username}</span>
        <span className="text-xs px-2 py-0.5 rounded-full" style={{ color, background: `${color}1a` }}>
          {node.rank}
        </span>
        {!root && <span className="text-[10px] text-muted">L{node.depth}</span>}
        <span className="text-xs text-gold-light font-medium">Invest: {usdt(node.total_invested)}</span>
      </div>
      {open && hasChildren && (
        <div className="border-l border-[var(--line)] ml-4">
          {node.children.map((c) => (
            <Node key={c.id} node={c} />
          ))}
        </div>
      )}
    </div>
  );
}

export default function NetworkTree({ root }: { root: TreeNode }) {
  return (
    <div className="glass p-4 overflow-x-auto">
      <Node node={root} root />
    </div>
  );
}
