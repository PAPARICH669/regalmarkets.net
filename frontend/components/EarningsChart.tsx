"use client";

import {
  Area,
  AreaChart,
  Bar,
  BarChart,
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

export default function EarningsChart({
  data,
  dataKey = "total",
  title = "Earnings — last 14 days",
  kind = "area",
  color = "#e7c873",
}: {
  data: Point[];
  dataKey?: "total" | "roi" | "matching" | "sponsor";
  title?: string;
  kind?: "area" | "bar";
  color?: string;
}) {
  const series = data.map((d) => ({ ...d, label: d.date.slice(5) }));
  const gradId = `grad_${dataKey}`;

  const axes = (
    <>
      <CartesianGrid strokeDasharray="3 3" stroke="rgba(255,255,255,0.06)" />
      <XAxis dataKey="label" stroke="#9b9bac" fontSize={11} />
      <YAxis stroke="#9b9bac" fontSize={11} width={38} />
      <Tooltip
        cursor={{ fill: "rgba(255,255,255,0.04)" }}
        contentStyle={{ background: "#0a1c40", border: "1px solid rgba(201,162,39,0.3)", borderRadius: 12, color: "#eef3fc" }}
        formatter={(v: number) => [`${Number(v).toFixed(2)} USDT`, ""]}
      />
    </>
  );

  return (
    <div className="glass p-5">
      <h3 className="font-semibold mb-4">{title}</h3>
      <ResponsiveContainer width="100%" height={260}>
        {kind === "bar" ? (
          <BarChart data={series}>
            {axes}
            <Bar dataKey={dataKey} fill={color} radius={[4, 4, 0, 0]} />
          </BarChart>
        ) : (
          <AreaChart data={series}>
            <defs>
              <linearGradient id={gradId} x1="0" y1="0" x2="0" y2="1">
                <stop offset="0%" stopColor={color} stopOpacity={0.5} />
                <stop offset="100%" stopColor={color} stopOpacity={0} />
              </linearGradient>
            </defs>
            {axes}
            <Area type="monotone" dataKey={dataKey} stroke={color} strokeWidth={2} fill={`url(#${gradId})`} />
          </AreaChart>
        )}
      </ResponsiveContainer>
    </div>
  );
}
