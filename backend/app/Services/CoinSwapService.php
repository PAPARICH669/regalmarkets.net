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
        $lastGood = (array) Cache::get(self::PRICE_KEY_GOOD, []);
        $fresh    = $this->fetchPrices();
        // Overlay fresh prices on the last-good set so any coin that failed to
        // fetch this cycle keeps its previous price instead of disappearing.
        $merged = array_merge($lastGood, $fresh);
        if (count($fresh) > 1) { // got at least one live price beyond USDT
            Cache::put(self::PRICE_KEY, $merged, (int) config('regal.coin_swap.price_ttl', 60));
            Cache::forever(self::PRICE_KEY_GOOD, $merged);
            return $merged;
        }
        return ! empty($lastGood) ? $lastGood : $merged;
    }

    protected function fetchPrices(): array
    {
        // USDT is always 1. Each source is fetched independently so one being
        // down never wipes the others; prices() keeps the last-good value for any
        // coin a cycle fails to fetch.
        return array_merge(['USDT' => 1.0], $this->fetchCoinGeckoPrices(), $this->fetchLbankPrices());
    }

    /** Coins priced via CoinGecko (coingecko_id set). @return array<string,float> */
    protected function fetchCoinGeckoPrices(): array
    {
        $ids = [];
        foreach ($this->coins() as $sym => $c) {
            if (! empty($c['coingecko_id'])) {
                $ids[$c['coingecko_id']] = strtoupper($sym);
            }
        }
        if (empty($ids)) {
            return [];
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
            $out = [];
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

    /** Coins priced via LBank public ticker (lbank_symbol set, e.g. XAUT). @return array<string,float> */
    protected function fetchLbankPrices(): array
    {
        $out = [];
        foreach ($this->coins() as $sym => $c) {
            if (empty($c['lbank_symbol'])) {
                continue;
            }
            try {
                $res = Http::timeout(8)->acceptJson()->get(
                    'https://api.lbkex.com/v2/ticker.do',
                    ['symbol' => $c['lbank_symbol']]
                );
                // Response: { data: [ { ticker: { latest: <price> } } ] }
                $latest = $res->successful() ? $res->json('data.0.ticker.latest') : null;
                if ($latest !== null && (float) $latest > 0) {
                    $out[strtoupper($sym)] = (float) $latest;
                } else {
                    Log::warning('LBank price fetch failed for ' . $c['lbank_symbol'] . ' (' . $res->status() . ')');
                }
            } catch (\Throwable $e) {
                Log::warning('LBank price error (' . $c['lbank_symbol'] . '): ' . $e->getMessage());
            }
        }
        return $out;
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

        // Min/max/per-day mirror the USDT withdrawal ($10 / $500 / 1-per-day —
        // max & per-day are enforced in WithdrawalService before the coin branch).
        // The only per-coin floor on the minimum is that the amount must cover the
        // network fee (else the member nets ~0); this only rises above $10 on a
        // high-fee network such as native Bitcoin.
        $baseMinUsdt = (float) $this->settings->get('min_withdrawal', 10);
        $coinMinUsdt = (float) ($cfg['min_usdt'] ?? 0); // per-coin USDT floor (e.g. XAUT $20)
        $minUsdt     = max($baseMinUsdt, $feeCoin * $rate, $coinMinUsdt);

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
                'min_usdt'    => (float) ($c['min_usdt'] ?? 0),
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
