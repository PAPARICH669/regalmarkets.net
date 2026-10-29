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
        $data = $request->validate([
            'txid'               => ['nullable', 'string', 'max:191'],
            // Actual coin amount the admin sent (coin swaps only) — source of truth.
            'coin_amount_actual' => ['nullable', 'numeric', 'min:0'],
        ]);
        $wasPending = $withdrawal->status === 'pending';
        $withdrawal = $this->withdrawals->approve(
            $withdrawal,
            $request->user(),
            $data['txid'] ?? null,
            $data['coin_amount_actual'] ?? null
        );
        $this->audit->log($request, 'withdrawal.approve', $withdrawal, ['amount' => $withdrawal->amount, 'coin' => $withdrawal->coin]);

        // Notify the member by email on a real approval (failure must not block it).
        if ($wasPending && $withdrawal->status === 'approved') {
            try {
                $u = $withdrawal->user;
                app(\App\Services\MailService::class)->sendWithdrawalApproved(
                    $u->email,
                    $u->name ?: $u->username,
                    number_format((float) $withdrawal->amount, 2),
                    number_format((float) $withdrawal->net_amount, 2),
                    $withdrawal->wallet_address,
                    $withdrawal->txid
                );
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Withdrawal approved email failed: ' . $e->getMessage());
            }
        }

        return response()->json(['message' => 'Withdrawal approved.', 'withdrawal' => $withdrawal]);
    }

    public function reject(Request $request, Withdrawal $withdrawal)
    {
        // A rejection reason is required — it is shown to the member and emailed to them.
        $data = $request->validate(['note' => ['required', 'string', 'min:3', 'max:255']]);
        $note = trim($data['note']);
        $wasPending = $withdrawal->status === 'pending';
        $withdrawal = $this->withdrawals->reject($withdrawal, $request->user(), $note);
        $this->audit->log($request, 'withdrawal.reject', $withdrawal, ['reason' => $note]);

        // Notify the member by email with the reason (failure must not block it).
        if ($wasPending && $withdrawal->status === 'rejected') {
            try {
                $u = $withdrawal->user;
                app(\App\Services\MailService::class)->sendWithdrawalRejected(
                    $u->email,
                    $u->name ?: $u->username,
                    number_format((float) $withdrawal->amount, 2),
                    $note
                );
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Withdrawal rejected email failed: ' . $e->getMessage());
            }
        }

        return response()->json(['message' => 'Withdrawal rejected and refunded.', 'withdrawal' => $withdrawal]);
    }
}
