<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DepositService;
use App\Services\SettingsService;
use Illuminate\Http\Request;

class DepositController extends Controller
{
    public function __construct(protected DepositService $deposits, protected SettingsService $settings) {}

    public function index(Request $request)
    {
        return $request->user()->deposits()->latest()->paginate(15);
    }

    public function store(Request $request)
    {
        $min = (float) $this->settings->get('min_deposit');

        $data = $request->validate([
            'amount' => ['required', 'numeric', "min:{$min}"],
            'txid'   => ['nullable', 'string', 'max:191'],
            'proof'  => ['required', 'image', 'max:4096'],
        ], [
            'proof.required' => 'A payment proof image is required for every deposit.',
            'proof.image'    => 'The payment proof must be an image (JPG/PNG).',
        ]);

        $proofPath = $request->file('proof')->store('proofs', config('regal.proof_disk', 'local'));

        $deposit = $this->deposits->request($request->user(), $data['amount'], $data['txid'] ?? null, $proofPath);

        app(\App\Services\TelegramService::class)->notify('💰 New Deposit Request', [
            'User'   => '@' . $request->user()->username,
            'Amount' => number_format((float) $deposit->amount, 2) . ' USDT',
            'TXID'   => $deposit->txid ?? '—',
        ]);

        return response()->json([
            'message' => 'Deposit submitted and pending admin approval.',
            'deposit' => $deposit,
        ], 201);
    }
}
