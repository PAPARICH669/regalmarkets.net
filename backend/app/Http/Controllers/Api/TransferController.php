<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transfer;
use App\Services\SettingsService;
use App\Services\TransferService;
use Illuminate\Http\Request;

class TransferController extends Controller
{
    public function __construct(protected TransferService $transfers, protected SettingsService $settings) {}

    public function config()
    {
        return response()->json([
            'min'         => (float) $this->settings->get('min_transfer'),
            'fee_percent' => (float) $this->settings->get('transfer_fee_percent'),
        ]);
    }

    public function index(Request $request)
    {
        $id = $request->user()->id;
        return Transfer::where('from_user_id', $id)->orWhere('to_user_id', $id)
            ->with(['fromUser:id,username', 'toUser:id,username'])
            ->latest()->paginate(15);
    }

    public function self(Request $request)
    {
        $data = $request->validate(['amount' => ['required', 'numeric', 'min:0.00000001']]);
        $transfer = $this->transfers->selfEtoA($request->user(), $data['amount']);
        return response()->json([
            'message'  => "Transferred to A-WALLET. Fee {$transfer->fee} USDT, received {$transfer->net_amount} USDT.",
            'transfer' => $transfer,
        ], 201);
    }

    public function member(Request $request)
    {
        $data = $request->validate([
            'to'     => ['required', 'string'], // username or email
            'amount' => ['required', 'numeric', 'min:0.00000001'],
        ]);
        $transfer = $this->transfers->memberToMember($request->user(), $data['to'], $data['amount']);
        return response()->json(['message' => 'Transfer sent.', 'transfer' => $transfer], 201);
    }
}
