"use client";

import { useState } from "react";
import { Plus, Minus } from "lucide-react";

const ITEMS = [
  { q: "How does the 200% ROI work?", a: "Every investment package returns 200% of your principal. ROI is paid daily (default 1%/day) into your E-WALLET until the package reaches 200% total, then it completes automatically." },
  { q: "What is the matching bonus?", a: "An unlimited-level override on your downline's daily ROI. Each upline earns the difference between their rank percentage and the highest already paid below them. Same-rank uplines stop the rollup." },
  { q: "How are sponsor bonuses paid?", a: "Instantly on deposit activation across 5 unilevel levels: 10%, 5%, 3%, 2%, 1% — credited straight to your E-WALLET." },
  { q: "How do withdrawals work?", a: "Withdraw from your E-WALLET (min 10 USDT, max 1,000 USDT daily). A configurable fee applies and requests are processed within 72 working hours." },
  { q: "Why is the system offline at night?", a: "Daily maintenance runs 00:00–06:59 (Asia/Kuala_Lumpur). Login, withdraw and transfer are paused and resume automatically at 07:00." },
  { q: "Can I move money between wallets?", a: "You can transfer E-WALLET → A-WALLET and send A-WALLET funds to other members. A-WALLET → E-WALLET is never allowed." },
];

export default function Faq() {
  const [open, setOpen] = useState<number | null>(0);
  return (
    <div className="max-w-3xl mx-auto space-y-3">
      {ITEMS.map((item, i) => (
        <div key={i} className="glass overflow-hidden">
          <button
            onClick={() => setOpen(open === i ? null : i)}
            className="w-full flex items-center justify-between px-5 py-4 text-left"
          >
            <span className="font-medium">{item.q}</span>
            {open === i ? <Minus size={18} className="text-gold-light" /> : <Plus size={18} className="text-gold-light" />}
          </button>
          {open === i && <p className="px-5 pb-5 text-sm text-muted">{item.a}</p>}
        </div>
      ))}
    </div>
  );
}
