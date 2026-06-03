"use client";

import {
  Area,
  AreaChart,
  CartesianGrid,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
} from "recharts";

interface Point {
  date: string;
  roi: number;
  matching: number;
  sponsor: number;
  total: number;
}

export default function EarningsChart({ data }: { data: Point[] }) {
  const series = data.map((d) => ({ ...d, label: d.date.slice(5) }));
  return (
    <div className="glass p-5">
      <h3 className="font-semibold mb-4">Earnings — last 14 days</h3>
      <ResponsiveContainer width="100%" height={260}>
        <AreaChart data={series}>
          <defs>
            <linearGradient id="gGold" x1="0" y1="0" x2="0" y2="1">
              <stop offset="0%" stopColor="#e7c873" stopOpacity={0.5} />
              <stop offset="100%" stopColor="#c9a227" stopOpacity={0} />
            </linearGradient>
          </defs>
          <CartesianGrid strokeDasharray="3 3" stroke="rgba(255,255,255,0.06)" />
          <XAxis dataKey="label" stroke="#9b9bac" fontSize={11} />
          <YAxis stroke="#9b9bac" fontSize={11} width={38} />
          <Tooltip
            contentStyle={{
              background: "#0d0d14",
              border: "1px solid rgba(201,162,39,0.3)",
              borderRadius: 12,
              color: "#ededf2",
            }}
            formatter={(v: number) => [`${v.toFixed(2)} USDT`, ""]}
          />
          <Area type="monotone" dataKey="total" stroke="#e7c873" strokeWidth={2} fill="url(#gGold)" />
        </AreaChart>
      </ResponsiveContainer>
    </div>
  );
}
