<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Coin-swap pricing & catalog for withdrawals.
 *
 * Members withdraw USDT from their E-WALLET but may choose to RECEIVE the payout
 * in BTC / ETH / SOL (all BEP20). The estimate uses the live CoinGecko price with
 * an admin markup; the FINAL amount is whatever the admin actually sends (Model A).
 *
 * The exchange/venue the admin uses is NEVER exposed — members only ever see the
 * "system rate".
 */
class CoinSwapService
{
    protected const PRICE_KEY      = 'coin_swap.prices';
    protected const PRICE_KEY_GOOD = 'coin_swap.prices.lastgood';

    public function __construct(protected SettingsService $settings) {}

    /** Whole feature toggle (admin can switch it off instantly). Default ON. */
    public function enabled(): bool
    {
        return (bool) $this->settings->get('coin_swap_enabled', true);
    }

    /** Coin catalog from config: symbol => [name, coingecko_id, network, dp, min, fee]. */
    public function coins(): array
    {
        return (array) config('regal.coin_swap.coins', []);
    }

    public function isCoin(string $symbol): bool
    {
        return array_key_exists(strtoupper($symbol), $this->coins());
    }

    /** Markup % applied over the CoinGecko price (admin-tunable). */
    public function markupPercent(): float
    {
        return (float) $this->settings->get('coin_swap_markup_percent', config('regal.coin_swap.markup_percent', 2.0));
    }

    /**
     * CoinGecko USD prices keyed by SYMBOL (USDT => 1). Cached for price_ttl
     * seconds, with a stale "last good" fallback so a transient CoinGecko outage
     * never blocks the estimate.
     */
    public function prices(): array
    {
        $cached = Cache::get(self::PRICE_KEY);
        if (is_array($cached)) {
            return $cached;
        }

        $fresh = $this->fetchPrices();
        if (! empty($fresh)) {
            $ttl = (int) config('regal.coin_swap.price_ttl', 60);
            Cache::put(self::PRICE_KEY, $fresh, $ttl);
            Cache::forever(self::PRICE_KEY_GOOD, $fresh);
            return $fresh;
        }

        // Transient failure — serve the last known-good prices if we have any.
        return (array) Cache::get(self::PRICE_KEY_GOOD, []);
    }

    protected function fetchPrices(): array
    {
        $ids = [];
        foreach ($this->coins() as $sym => $c) {
            if (! empty($c['coingecko_id'])) {
                $ids[$c['coingecko_id']] = strtoupper($sym);
            }
        }
        if (empty($ids)) {
            return ['USDT' => 1.0];
        }

        try {
            $res = Http::timeout(8)->acceptJson()->get(
                'https://api.coingecko.com/api/v3/simple/price',
                ['ids' => implode(',', array_keys($ids)), 'vs_currencies' => 'usd']
            );
            if (! $res->successful()) {
                Log::warning('CoinGecko price fetch failed (' . $res->status() . ')');
                return [];
            }
            $out = ['USDT' => 1.0];
            foreach ($res->json() as $id => $row) {
                $sym = $ids[$id] ?? null;
                $usd = $row['usd'] ?? null;
                if ($sym && $usd > 0) {
                    $out[$sym] = (float) $usd;
                }
            }
            return $out;
        } catch (\Throwable $e) {
            Log::warning('CoinGecko price error: ' . $e->getMessage());
            return [];
        }
    }

    /** System rate = CoinGecko price × (1 + markup%). USDT is always 1. Null if price unavailable. */
    public function systemRate(string $symbol): ?float
    {
        $symbol = strtoupper($symbol);
        if ($symbol === 'USDT') {
            return 1.0;
        }
        $price = $this->prices()[$symbol] ?? null;
        if (! $price || $price <= 0) {
            return null;
        }
        return $price * (1 + $this->markupPercent() / 100);
    }

    /**
     * Quote a USDT withdrawal amount into a coin. Returns null if the coin is
     * unknown or its price is unavailable.
     *
     * @return array{coin:string,network:string,dp:int,system_rate:float,fee_coin:float,
     *               gross_coin:float,net_coin:float,min_coin:float,min_usdt:float,meets_min:bool}|null
     */
    public function quote(string $symbol, float $usdtAmount): ?array
    {
        $symbol = strtoupper($symbol);
        $cfg = $this->coins()[$symbol] ?? null;
        if (! $cfg) {
            return null;
        }
        $rate = $this->systemRate($symbol);
        if ($rate === null) {
            return null;
        }

        $feeCoin  = (float) ($cfg['fee'] ?? 0);
        $minCoin  = (float) ($cfg['min'] ?? 0);
        $gross    = $rate > 0 ? $usdtAmount / $rate : 0.0;
        $net      = max($gross - $feeCoin, 0.0);

        $baseMinUsdt = (float) $this->settings->get('min_withdrawal', 10);
        $minUsdt     = max($baseMinUsdt, ($minCoin + $feeCoin) * $rate);

        return [
            'coin'        => $symbol,
            'network'     => $cfg['network'] ?? 'BEP20',
            'dp'          => (int) ($cfg['dp'] ?? 8),
            'system_rate' => $rate,
            'fee_coin'    => $feeCoin,
            'gross_coin'  => $gross,
            'net_coin'    => $net,
            'min_coin'    => $minCoin,
            'min_usdt'    => $minUsdt,
            'meets_min'   => $usdtAmount >= $minUsdt,
        ];
    }

    /** Public catalog for the withdrawal form: coins with live system rates. */
    public function catalog(): array
    {
        $prices = $this->prices();
        $out = [];
        foreach ($this->coins() as $sym => $c) {
            $sym  = strtoupper($sym);
            $rate = $this->systemRate($sym);
            $out[] = [
                'coin'        => $sym,
                'name'        => $c['name'] ?? $sym,
                'network'     => $c['network'] ?? 'BEP20',
                'dp'          => (int) ($c['dp'] ?? 8),
                'min'         => (float) ($c['min'] ?? 0),
                'fee'         => (float) ($c['fee'] ?? 0),
                'system_rate' => $rate,               // null if price unavailable
                'available'   => $sym === 'USDT' || $rate !== null,
            ];
        }
        return $out;
    }
}
