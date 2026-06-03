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

        return response()->json([
            'message'    => 'Withdrawal requested. Processing within 72 working hours.',
            'withdrawal' => $withdrawal,
        ], 201);
    }

    public function config()
    {
        return response()->json([
            'min'         => (float) $this->settings->get('min_withdrawal'),
            'max_daily'   => (float) $this->settings->get('max_withdrawal_daily'),
            'fee_percent' => (float) $this->settings->get('withdrawal_fee_percent'),
            'processing_hours' => config('regal.withdrawal.processing_hours', 72),
        ]);
    }
}
