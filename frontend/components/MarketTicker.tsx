"use client";

import { useEffect, useRef } from "react";

/**
 * Live crypto ticker (TradingView Ticker Tape) shown across the top of the
 * member dashboard — the top 20 coins by market cap (stablecoins excluded).
 * Prices are live (Binance feed), dark theme to match the dashboard. The list
 * is a fixed set of symbols; refresh it occasionally as market-cap ranking
 * shifts. Free widget.
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
        { proName: "BINANCE:BTCUSDT", title: "BTC" },
        { proName: "BINANCE:ETHUSDT", title: "ETH" },
        { proName: "BINANCE:BNBUSDT", title: "BNB" },
        { proName: "BINANCE:SOLUSDT", title: "SOL" },
        { proName: "BINANCE:XRPUSDT", title: "XRP" },
        { proName: "BINANCE:DOGEUSDT", title: "DOGE" },
        { proName: "BINANCE:ADAUSDT", title: "ADA" },
        { proName: "BINANCE:TRXUSDT", title: "TRX" },
        { proName: "BINANCE:AVAXUSDT", title: "AVAX" },
        { proName: "BINANCE:LINKUSDT", title: "LINK" },
        { proName: "BINANCE:TONUSDT", title: "TON" },
        { proName: "BINANCE:SHIBUSDT", title: "SHIB" },
        { proName: "BINANCE:DOTUSDT", title: "DOT" },
        { proName: "BINANCE:SUIUSDT", title: "SUI" },
        { proName: "BINANCE:BCHUSDT", title: "BCH" },
        { proName: "BINANCE:LTCUSDT", title: "LTC" },
        { proName: "BINANCE:NEARUSDT", title: "NEAR" },
        { proName: "BINANCE:POLUSDT", title: "POL" },
        { proName: "BINANCE:APTUSDT", title: "APT" },
        { proName: "BINANCE:XLMUSDT", title: "XLM" },
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
