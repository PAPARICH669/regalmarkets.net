<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Read-only BEP20 USDT deposit verification (Cadangan A). Given a transaction
 * hash, it fetches the REAL transaction receipt from a public BSC JSON-RPC node
 * (no API key) and confirms it is a genuine USDT transfer INTO our deposit
 * address, returning the on-chain amount, sender, and confirmation count. It
 * holds NO private keys and never moves funds — it only reads the chain, so a
 * server compromise cannot touch deposits.
 *
 * Result shape (verify()):
 *   found        bool   — the tx exists on-chain yet (false = not indexed / wrong hash)
 *   success      bool   — the tx succeeded (status 0x1)
 *   matched      bool   — a USDT transfer to OUR address was found in the logs
 *   amount       string — decimal USDT (8 dp) actually transferred to us
 *   from         string — sender address (lowercased)
 *   block        int    — block number of the tx
 *   confirmations int   — current height − tx block
 *   error        ?string— machine reason when unusable (api_error, etc.)
 */
class BscVerifier
{
    /** keccak256("Transfer(address,address,uint256)") */
    private const TRANSFER_TOPIC = '0xddf252ad1be2c89b69c2b068fc378daa952ba7f163c4a11628f55a4df523b3ef';

    public function isConfigured(): bool
    {
        return count((array) config('regal.deposit.rpc_urls')) > 0;
    }

    public function verify(string $txHash): array
    {
        $base = [
            'found' => false, 'success' => false, 'matched' => false,
            'amount' => '0', 'from' => null, 'block' => 0, 'confirmations' => 0, 'error' => null,
        ];

        $usdt    = strtolower(config('regal.deposit.usdt_contract'));
        $ourAddr = strtolower(trim((string) app(SettingsService::class)->get('deposit_address')));
        if (! preg_match('/^0x[0-9a-f]{40}$/', $ourAddr)) {
            return array_merge($base, ['error' => 'deposit_address_unset']);
        }

        $receipt = $this->rpc('eth_getTransactionReceipt', [$txHash]);
        if ($receipt === null) {
            return array_merge($base, ['error' => 'api_error']);
        }
        if ($receipt === false || empty($receipt['blockNumber'])) {
            return $base; // not found / not yet indexed
        }

        $found   = true;
        $success = strtolower((string) ($receipt['status'] ?? '0x0')) === '0x1';
        $block   = (int) $this->hexToDec((string) $receipt['blockNumber']);

        $matched = false; $amount = '0'; $from = null;
        foreach (($receipt['logs'] ?? []) as $log) {
            $topics = $log['topics'] ?? [];
            if (count($topics) < 3) continue;
            if (strtolower((string) ($log['address'] ?? '')) !== $usdt) continue;
            if (strtolower((string) $topics[0]) !== self::TRANSFER_TOPIC) continue;

            $to = '0x' . strtolower(substr($topics[2], -40));
            if ($to !== $ourAddr) continue;

            $matched = true;
            $from    = '0x' . strtolower(substr($topics[1], -40));
            $raw     = $this->hexToDec((string) ($log['data'] ?? '0x0'));
            $amount  = bcdiv($raw, bcpow('10', '18'), 8); // USDT BEP20 = 18 decimals
            break;
        }

        $confirmations = 0;
        if ($block > 0) {
            $headHex = $this->rpc('eth_blockNumber', []);
            if (is_string($headHex)) {
                $head = (int) $this->hexToDec($headHex);
                $confirmations = max(0, $head - $block + 1);
            }
        }

        return compact('found', 'success', 'matched', 'amount', 'from', 'block', 'confirmations') + ['error' => null];
    }

    /**
     * Call an eth JSON-RPC method against the configured public BSC nodes,
     * trying each in turn until one answers.
     * Returns: the decoded `result` (array|string), false when a node returns an
     * explicit null result (tx not found / not yet mined), or null when every
     * endpoint failed (transport error / all down).
     */
    protected function rpc(string $method, array $params)
    {
        $payload = ['jsonrpc' => '2.0', 'id' => 1, 'method' => $method, 'params' => $params];

        foreach ((array) config('regal.deposit.rpc_urls') as $url) {
            try {
                $resp = Http::timeout(12)->acceptJson()->post($url, $payload);
                if (! $resp->ok()) continue;

                $json = $resp->json();
                if (isset($json['error'])) {
                    Log::warning('BSC RPC node error', ['url' => $url, 'method' => $method, 'error' => $json['error']]);
                    continue;
                }
                if (array_key_exists('result', $json)) {
                    return $json['result'] === null ? false : $json['result'];
                }
            } catch (\Throwable $e) {
                Log::warning('BSC RPC failed (' . $url . '): ' . $e->getMessage());
            }
        }

        return null; // every endpoint failed
    }

    /** Big-integer hex → decimal string (values exceed PHP int range). */
    protected function hexToDec(string $hex): string
    {
        $hex = strtolower($hex);
        if (str_starts_with($hex, '0x')) $hex = substr($hex, 2);
        $hex = ltrim($hex, '0');
        if ($hex === '') return '0';

        $dec = '0';
        foreach (str_split($hex) as $c) {
            $dec = bcadd(bcmul($dec, '16'), (string) hexdec($c));
        }
        return $dec;
    }
}
