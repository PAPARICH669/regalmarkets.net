<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Coin-swap pricing, network catalog & address validation for withdrawals.
 *
 * Members withdraw USDT from their E-WALLET but may RECEIVE the payout in
 * BTC / ETH / SOL. Each coin can support multiple NETWORKS (e.g. BTC on BEP20 or
 * native Bitcoin), each with its own fee and address format. The estimate uses
 * the live CoinGecko price with an admin markup; the FINAL amount is whatever the
 * admin actually sends (Model A). The venue is never exposed — members only see
 * the "system rate".
 */
class CoinSwapService
{
    protected const PRICE_KEY      = 'coin_swap.prices';
    protected const PRICE_KEY_GOOD = 'coin_swap.prices.lastgood';

    public function __construct(protected SettingsService $settings) {}

    public function enabled(): bool
    {
        return (bool) $this->settings->get('coin_swap_enabled', true);
    }

    /** Coin catalog from config: symbol => [name, coingecko_id, dp, min, networks]. */
    public function coins(): array
    {
        return (array) config('regal.coin_swap.coins', []);
    }

    public function isCoin(string $symbol): bool
    {
        return array_key_exists(strtoupper($symbol), $this->coins());
    }

    /** Networks available for a coin: [NETWORK => [fee, type, field]]. */
    public function networksFor(string $symbol): array
    {
        return (array) ($this->coins()[strtoupper($symbol)]['networks'] ?? []);
    }

    /**
     * Resolve a coin+network to its config; falls back to the coin's first network
     * when $network is null/blank. Returns null if the coin or network is unknown.
     */
    public function network(string $symbol, ?string $network = null): ?array
    {
        $nets = $this->networksFor($symbol);
        if (empty($nets)) {
            return null;
        }
        if ($network === null || $network === '') {
            $network = array_key_first($nets);
        }
        $network = strtoupper($network);
        if (! isset($nets[$network])) {
            return null;
        }
        return ['network' => $network] + $nets[$network];
    }

    /** The user column holding the payout address for this coin+network. */
    public function addressField(string $symbol, ?string $network = null): ?string
    {
        return $this->network($symbol, $network)['field'] ?? null;
    }

    public function markupPercent(): float
    {
        return (float) $this->settings->get('coin_swap_markup_percent', config('regal.coin_swap.markup_percent', 2.0));
    }

    public function prices(): array
    {
        $cached = Cache::get(self::PRICE_KEY);
        if (is_array($cached)) {
            return $cached;
        }
        $fresh = $this->fetchPrices();
        if (! empty($fresh)) {
            Cache::put(self::PRICE_KEY, $fresh, (int) config('regal.coin_swap.price_ttl', 60));
            Cache::forever(self::PRICE_KEY_GOOD, $fresh);
            return $fresh;
        }
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

    /** System rate = CoinGecko price × (1 + markup%). USDT is always 1. Null if unavailable. */
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
     * Quote a USDT amount into a coin on a specific network. Returns null if the
     * coin/network is unknown or the price is unavailable.
     */
    public function quote(string $symbol, ?string $network, float $usdtAmount): ?array
    {
        $symbol = strtoupper($symbol);
        $cfg = $this->coins()[$symbol] ?? null;
        $nw  = $this->network($symbol, $network);
        if (! $cfg || ! $nw) {
            return null;
        }
        $rate = $this->systemRate($symbol);
        if ($rate === null) {
            return null;
        }

        $feeCoin  = (float) ($nw['fee'] ?? 0);
        $minCoin  = (float) ($cfg['min'] ?? 0);
        $dp       = (int) ($cfg['dp'] ?? 8);
        $gross    = $rate > 0 ? $usdtAmount / $rate : 0.0;
        $net      = max($gross - $feeCoin, 0.0);

        $baseMinUsdt = (float) $this->settings->get('min_withdrawal', 10);
        $minUsdt     = max($baseMinUsdt, ($minCoin + $feeCoin) * $rate);

        return [
            'coin'        => $symbol,
            'network'     => $nw['network'],
            'type'        => $nw['type'] ?? 'evm',
            'field'       => $nw['field'] ?? null,
            'dp'          => $dp,
            'system_rate' => $rate,
            'fee_coin'    => $feeCoin,
            'gross_coin'  => $gross,
            'net_coin'    => $net,
            'min_coin'    => $minCoin,
            'min_usdt'    => $minUsdt,
            'meets_min'   => $usdtAmount >= $minUsdt,
        ];
    }

    /** Public catalog for the withdrawal form: coins + their networks + live rate. */
    public function catalog(): array
    {
        $out = [];
        foreach ($this->coins() as $sym => $c) {
            $sym  = strtoupper($sym);
            $rate = $this->systemRate($sym);
            $nets = [];
            foreach ((array) ($c['networks'] ?? []) as $net => $n) {
                $nets[] = [
                    'network'     => strtoupper($net),
                    'fee'         => (float) ($n['fee'] ?? 0),
                    'type'        => $n['type'] ?? 'evm',
                    'address_key' => $n['field'] ?? null,
                ];
            }
            $out[] = [
                'coin'        => $sym,
                'name'        => $c['name'] ?? $sym,
                'dp'          => (int) ($c['dp'] ?? 8),
                'min'         => (float) ($c['min'] ?? 0),
                'system_rate' => $rate,
                'available'   => $sym === 'USDT' || $rate !== null,
                'networks'    => $nets,
            ];
        }
        return $out;
    }

    /** Validate a payout address against a network address type. */
    public static function validateAddress(string $type, string $addr): bool
    {
        $addr = trim($addr);
        return match ($type) {
            'evm' => (bool) preg_match('/^0x[a-fA-F0-9]{40}$/', $addr),
            // Bitcoin: legacy/p2sh base58 (1.../3...) or bech32 (bc1...).
            'btc' => (bool) preg_match('/^(bc1[a-z0-9]{25,59}|[13][a-km-zA-HJ-NP-Z1-9]{25,34})$/', $addr),
            // Solana: base58, 32–44 chars.
            'sol' => (bool) preg_match('/^[1-9A-HJ-NP-Za-km-z]{32,44}$/', $addr),
            default => false,
        };
    }
}
