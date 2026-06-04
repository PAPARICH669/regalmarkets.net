<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SettingsService;
use App\Services\WithdrawalService;
use Illuminate\Http\Request;

class WithdrawalController extends Controller
{
    public function __construct(protected WithdrawalService $withdrawals, protected SettingsService $settings) {}

    public function index(Request $request)
    {
        return $request->user()->withdrawals()->latest()->paginate(15);
    }

    public function store(Request $request)
    {
        $min = (float) $this->settings->get('min_withdrawal');

        $data = $request->validate([
            'amount'         => ['required', 'numeric', "min:{$min}"],
            'wallet_address' => ['required', 'string', 'max:120'],
        ]);

        $withdrawal = $this->withdrawals->request($request->user(), $data['amount'], $data['wallet_address']);

        app(\App\Services\TelegramService::class)->notify('🏧 New Withdrawal Request', [
            'User'    => '@' . $request->user()->username,
            'Amount'  => number_format((float) $withdrawal->amount, 2) . ' USDT',
            'Net'     => number_format((float) $withdrawal->net_amount, 2) . ' USDT',
            'Address' => $withdrawal->wallet_address,
        ]);

        return response()->json([
            'message'    => 'Withdrawal requested. Processed within 72 working hours (Mon–Fri).',
            'withdrawal' => $withdrawal,
        ], 201);
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
        ]);
    }
}
