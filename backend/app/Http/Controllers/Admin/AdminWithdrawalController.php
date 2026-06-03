<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Withdrawal;
use App\Services\AuditService;
use App\Services\WithdrawalService;
use Illuminate\Http\Request;

class AdminWithdrawalController extends Controller
{
    public function __construct(protected WithdrawalService $withdrawals, protected AuditService $audit) {}

    public function index(Request $request)
    {
        $q = Withdrawal::with('user:id,username,email')->latest();
        if ($status = $request->query('status')) {
            $q->where('status', $status);
        }
        return $q->paginate(20);
    }

    public function approve(Request $request, Withdrawal $withdrawal)
    {
        $data = $request->validate(['txid' => ['nullable', 'string', 'max:191']]);
        $withdrawal = $this->withdrawals->approve($withdrawal, $request->user(), $data['txid'] ?? null);
        $this->audit->log($request, 'withdrawal.approve', $withdrawal, ['amount' => $withdrawal->amount]);
        return response()->json(['message' => 'Withdrawal approved.', 'withdrawal' => $withdrawal]);
    }

    public function reject(Request $request, Withdrawal $withdrawal)
    {
        $data = $request->validate(['note' => ['nullable', 'string', 'max:255']]);
        $withdrawal = $this->withdrawals->reject($withdrawal, $request->user(), $data['note'] ?? null);
        $this->audit->log($request, 'withdrawal.reject', $withdrawal);
        return response()->json(['message' => 'Withdrawal rejected and refunded.', 'withdrawal' => $withdrawal]);
    }
}
