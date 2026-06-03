<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transfer;
use App\Services\TransferService;
use Illuminate\Http\Request;

class TransferController extends Controller
{
    public function __construct(protected TransferService $transfers) {}

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
        return response()->json(['message' => 'Transferred from E-WALLET to A-WALLET.', 'transfer' => $transfer], 201);
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
