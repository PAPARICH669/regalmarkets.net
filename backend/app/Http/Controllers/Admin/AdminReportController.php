<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Deposit;
use App\Models\User;
use App\Models\Withdrawal;
use App\Services\SettingsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminReportController extends Controller
{
    /**
     * Monthly deposit summary for the admin (printable). Groups the three ways
     * money reaches a member's A-WALLET — form deposits, admin wallet
     * adjustments, and LD transfers — by month (Asia/Kuala_Lumpur), with dummy/
     * excluded accounts left out. Newest month first.
     */
    public function depositsMonthly(SettingsService $settings)
    {
        $excluded = User::where('is_dummy', true)->orWhere('exclude_from_stats', true)->pluck('id')->all();
        $off = '+08:00'; // Asia/Kuala_Lumpur

        $form = DB::table('deposits')->where('status', 'approved')->whereNotIn('user_id', $excluded)
            ->selectRaw("DATE_FORMAT(CONVERT_TZ(COALESCE(approved_at, created_at), '+00:00', ?), '%Y-%m') ym, COUNT(*) c, COALESCE(SUM(amount),0) s", [$off])
            ->groupBy('ym')->get();

        $adj = DB::table('wallet_transactions')->where('type', 'admin_adjust')->where('direction', 'credit')->where('wallet_type', 'A')->whereNotIn('user_id', $excluded)
            ->selectRaw("DATE_FORMAT(CONVERT_TZ(created_at, '+00:00', ?), '%Y-%m') ym, COUNT(*) c, COALESCE(SUM(amount),0) s", [$off])
            ->groupBy('ym')->get();

        $ld = DB::table('wallet_transactions')->where('type', 'ld_transfer')->where('direction', 'credit')->where('wallet_type', 'A')->whereNotIn('user_id', $excluded)
            ->selectRaw("DATE_FORMAT(CONVERT_TZ(created_at, '+00:00', ?), '%Y-%m') ym, COUNT(*) c, COALESCE(SUM(amount),0) s", [$off])
            ->groupBy('ym')->get();

        $months = [];
        $ensure = function (string $ym) use (&$months) {
            if (! isset($months[$ym])) {
                $months[$ym] = ['month' => $ym, 'form_count' => 0, 'form' => 0.0, 'adjust' => 0.0, 'adjust_count' => 0, 'ld' => 0.0, 'ld_count' => 0];
            }
        };
        foreach ($form as $r) { $ensure($r->ym); $months[$r->ym]['form_count'] = (int) $r->c; $months[$r->ym]['form'] = (float) $r->s; }
        foreach ($adj as $r)  { $ensure($r->ym); $months[$r->ym]['adjust_count'] = (int) $r->c; $months[$r->ym]['adjust'] = (float) $r->s; }
        foreach ($ld as $r)   { $ensure($r->ym); $months[$r->ym]['ld_count'] = (int) $r->c; $months[$r->ym]['ld'] = (float) $r->s; }

        krsort($months);
        $rows = array_map(function ($m) {
            $m['total'] = $m['form'] + $m['adjust'] + $m['ld'];
            return $m;
        }, array_values($months));

        return response()->json([
            'rows'               => $rows,
            'grand_total'        => array_sum(array_column($rows, 'total')),
            'form_total'         => array_sum(array_column($rows, 'form')),
            'adjust_total'       => array_sum(array_column($rows, 'adjust')),
            'ld_total'           => array_sum(array_column($rows, 'ld')),
            'adjustment_setting' => (float) $settings->get('total_deposit_adjustment', 0),
            'generated_at'       => now()->format('Y-m-d H:i'),
        ]);
    }

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
