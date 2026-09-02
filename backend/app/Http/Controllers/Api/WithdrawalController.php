<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CoinSwapService;
use App\Services\SettingsService;
use App\Services\WithdrawalService;
use Illuminate\Http\Request;

class WithdrawalController extends Controller
{
    public function __construct(
        protected WithdrawalService $withdrawals,
        protected SettingsService $settings,
        protected CoinSwapService $coins,
    ) {}

    public function index(Request $request)
    {
        return $request->user()->withdrawals()->latest()->paginate(15);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'amount'  => ['required', 'numeric', 'min:0.00000001'],
            'coin'    => ['nullable', 'string', 'max:12'],
            'network' => ['nullable', 'string', 'max:20'],
        ]);

        $user    = $request->user();
        $coin    = strtoupper(trim($data['coin'] ?? 'USDT'));
        $network = isset($data['network']) ? strtoupper(trim($data['network'])) : null;

        if ($coin !== 'USDT' && ! $this->coins->enabled()) {
            return response()->json(['message' => 'Coin-swap withdrawals are currently unavailable. Please withdraw in USDT.'], 422);
        }

        // Resolve the stored, admin-approved payout address for this coin+network.
        $nw = $this->coins->network($coin, $network);
        if ($nw === null) {
            return response()->json(['message' => 'Unknown coin/network selected.'], 422);
        }
        $network = $nw['network'];
        $field   = $nw['field'];

        // The payout address is LOCKED to the member's stored address for that
        // coin+network — members cannot redirect their own withdrawals.
        $address = trim((string) $user->{$field});
        if ($address === '') {
            return response()->json([
                'message' => "No {$coin} ({$network}) address is set on your account yet. Please add it in your Profile first.",
            ], 422);
        }

        $withdrawal = $this->withdrawals->request($user, $data['amount'], $coin, $network, $address);

        // Telegram admin alert — always fires, now with coin details for swaps.
        $lines = [
            'User'   => '@' . $user->username,
            'Amount' => number_format((float) $withdrawal->amount, 2) . ' USDT',
        ];
        if ($withdrawal->coin === 'USDT') {
            $lines['Net'] = number_format((float) $withdrawal->net_amount, 2) . ' USDT';
        } else {
            $lines['Receive in'] = $withdrawal->coin . ' (' . $withdrawal->network . ')';
            $lines['Est. net']   = $this->trimCoin($withdrawal->coin_amount_est) . ' ' . $withdrawal->coin;
        }
        $lines['Address'] = $withdrawal->coin_address;
        app(\App\Services\TelegramService::class)->notify('🏧 New Withdrawal Request', $lines);

        return response()->json([
            'message'    => 'Withdrawal requested. Processed within 72 working hours (Mon–Fri).',
            'withdrawal' => $withdrawal,
        ], 201);
    }

    protected function trimCoin($v): string
    {
        $s = rtrim(rtrim((string) $v, '0'), '.');
        return $s === '' ? '0' : $s;
    }

    public function config()
    {
        return response()->json([
            'min'          => (float) $this->settings->get('min_withdrawal'),
            'max_amount'   => (float) $this->settings->get('max_withdrawal_daily'),
            'fee_flat'     => (float) $this->settings->get('withdrawal_fee'),
            'max_per_day'  => (int) $this->settings->get('withdrawal_max_per_day'),
            'processing_hours' => config('regal.withdrawal.processing_hours', 72),
            'window_start' => $this->settings->get('withdrawal_window_start'),
            'window_end'   => $this->settings->get('withdrawal_window_end'),
            // Coin-swap: catalog with live system rates (null price => unavailable).
            'coin_swap_enabled' => $this->coins->enabled(),
            'coins'             => $this->coins->catalog(),
        ]);
    }

    /** Live coin catalog + rates (polled by the withdraw form for a fresh estimate). */
    public function coinRates()
    {
        return response()->json([
            'enabled' => $this->coins->enabled(),
            'coins'   => $this->coins->catalog(),
        ]);
    }
}
