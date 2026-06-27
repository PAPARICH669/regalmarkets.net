"use client";

import { useEffect, useRef } from "react";

/**
 * Live market ticker (TradingView Ticker Tape) shown across the top of the
 * member dashboard — Gold (XAUUSD), Silver, major forex pairs, and BTC/ETH.
 * Free widget, live daily prices, dark theme to match the dashboard.
 */
export default function MarketTicker() {
  const ref = useRef<HTMLDivElement>(null);

  useEffect(() => {
    const host = ref.current;
    if (!host || host.querySelector("script")) return; // avoid double-inject (StrictMode)

    const widget = document.createElement("div");
    widget.className = "tradingview-widget-container__widget";
    host.appendChild(widget);

    const script = document.createElement("script");
    script.src =
      "https://s3.tradingview.com/external-embedding/embed-widget-ticker-tape.js";
    script.async = true;
    script.innerHTML = JSON.stringify({
      symbols: [
        { proName: "OANDA:XAUUSD", title: "Gold" },
        { proName: "OANDA:XAGUSD", title: "Silver" },
        { proName: "FX:EURUSD", title: "EUR/USD" },
        { proName: "FX:GBPUSD", title: "GBP/USD" },
        { proName: "FX:USDJPY", title: "USD/JPY" },
        { proName: "FX:AUDUSD", title: "AUD/USD" },
        { proName: "BINANCE:BTCUSDT", title: "BTC/USD" },
        { proName: "BINANCE:ETHUSDT", title: "ETH/USD" },
      ],
      showSymbolLogo: true,
      colorTheme: "dark",
      isTransparent: true,
      displayMode: "adaptive",
      locale: "en",
    });
    host.appendChild(script);
  }, []);

  return (
    <div
      className="tradingview-widget-container border-b border-[var(--line)] bg-[var(--surface)]"
      ref={ref}
    />
  );
}
