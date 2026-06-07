<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Deposit;
use App\Services\AuditService;
use App\Services\DepositService;
use Illuminate\Http\Request;

class AdminDepositController extends Controller
{
    public function __construct(protected DepositService $deposits, protected AuditService $audit) {}

    public function index(Request $request)
    {
        $q = Deposit::with('user:id,username,email')->latest();
        if ($status = $request->query('status')) {
            $q->where('status', $status);
        }
        return $q->paginate(20);
    }

    public function approve(Request $request, Deposit $deposit)
    {
        $wasPending = $deposit->status === 'pending';
        $deposit = $this->deposits->approve($deposit, $request->user());
        $this->audit->log($request, 'deposit.approve', $deposit, ['amount' => $deposit->amount]);

        if ($wasPending && $deposit->status === 'approved') {
            try {
                $u = $deposit->user;
                app(\App\Services\MailService::class)->sendDepositApproved(
                    $u->email, $u->name ?: $u->username, number_format((float) $deposit->amount, 2)
                );
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Deposit approved email failed: ' . $e->getMessage());
            }
        }

        return response()->json(['message' => 'Deposit approved.', 'deposit' => $deposit]);
    }

    public function reject(Request $request, Deposit $deposit)
    {
        $data = $request->validate(['note' => ['nullable', 'string', 'max:255']]);
        $deposit = $this->deposits->reject($deposit, $request->user(), $data['note'] ?? null);
        $this->audit->log($request, 'deposit.reject', $deposit);
        return response()->json(['message' => 'Deposit rejected.', 'deposit' => $deposit]);
    }
}
