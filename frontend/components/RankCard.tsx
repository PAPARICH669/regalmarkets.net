"use client";

import { useState } from "react";
import { Crown, Mail, CalendarDays } from "lucide-react";
import { useAuth } from "@/lib/auth";
import { shortDate } from "@/lib/format";

// Rank name -> logo file in /public/ranks (user/fan/senior/team-leader/group-leader .jpg).
function rankSlug(rank?: string) {
  return (rank || "USER").toLowerCase().replace(/\s+/g, "-");
}

export default function RankCard() {
  const user = useAuth((s) => s.user);
  const [imgOk, setImgOk] = useState(true);
  if (!user) return null;

  const logo = `/ranks/${rankSlug(user.rank)}.jpg`;

  return (
    <div className="glass p-6 flex items-center gap-5">
      {/* Rank logo */}
      <div className="shrink-0">
        {imgOk ? (
          // eslint-disable-next-line @next/next/no-img-element
          <img
            src={logo}
            alt={`${user.rank} rank`}
            onError={() => setImgOk(false)}
            className="w-20 h-20 sm:w-24 sm:h-24 object-cover rounded-xl border border-gold-light/20 drop-shadow-[0_2px_10px_rgba(201,162,39,0.35)]"
          />
        ) : (
          <span className="inline-grid place-items-center w-20 h-20 rounded-full gold-gradient text-black">
            <Crown size={34} />
          </span>
        )}
      </div>

      {/* Details */}
      <div className="min-w-0">
        <p className="text-xl font-bold truncate">{user.nickname || user.username}</p>
        <span className="inline-block mt-1 text-xs font-semibold px-3 py-1 rounded-full bg-gold-light/10 text-gold-light border border-gold-light/30">
          {user.rank}
        </span>
        <p className="mt-3 text-sm text-muted flex items-center gap-2 truncate">
          <Mail size={14} className="text-gold-light shrink-0" /> {user.email}
        </p>
        {user.created_at && (
          <p className="mt-1 text-sm text-muted flex items-center gap-2">
            <CalendarDays size={14} className="text-gold-light shrink-0" /> Member since {shortDate(user.created_at)}
          </p>
        )}
      </div>
    </div>
  );
}
