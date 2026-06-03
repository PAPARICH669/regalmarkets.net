<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Deposit;
use App\Models\User;
use App\Models\Withdrawal;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminReportController extends Controller
{
    public function export(Request $request, string $type): StreamedResponse
    {
        [$headers, $rows] = match ($type) {
            'deposits'    => $this->deposits(),
            'withdrawals' => $this->withdrawals(),
            'members'     => $this->members(),
            default       => [['error'], [['unknown report type']]],
        };

        $filename = "regal_{$type}_" . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($headers, $rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $headers);
            foreach ($rows as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    protected function deposits(): array
    {
        $headers = ['ID', 'User', 'Amount', 'TXID', 'Status', 'Created', 'Approved'];
        $rows = Deposit::with('user:id,username')->get()->map(fn ($d) => [
            $d->id, $d->user?->username, $d->amount, $d->txid, $d->status, $d->created_at, $d->approved_at,
        ])->toArray();
        return [$headers, $rows];
    }

    protected function withdrawals(): array
    {
        $headers = ['ID', 'User', 'Amount', 'Fee', 'Net', 'Address', 'TXID', 'Status', 'Created', 'Processed'];
        $rows = Withdrawal::with('user:id,username')->get()->map(fn ($w) => [
            $w->id, $w->user?->username, $w->amount, $w->fee, $w->net_amount, $w->wallet_address, $w->txid, $w->status, $w->created_at, $w->processed_at,
        ])->toArray();
        return [$headers, $rows];
    }

    protected function members(): array
    {
        $headers = ['ID', 'Username', 'Email', 'Rank', 'Sponsor', 'Total Fund', 'Total Invested', 'Frozen', 'Joined'];
        $rows = User::members()->with('rank:id,name', 'sponsor:id,username')->get()->map(fn ($u) => [
            $u->id, $u->username, $u->email, $u->rankName(), $u->sponsor?->username, $u->total_fund, $u->total_invested, $u->is_frozen ? 'yes' : 'no', $u->created_at,
        ])->toArray();
        return [$headers, $rows];
    }
}
