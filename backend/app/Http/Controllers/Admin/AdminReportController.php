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

    /**
     * Downloadable CSV: for a given PARENT member, each of its direct downlines
     * is treated as a "group" (that leader + their whole downline). Shows each
     * group's deposit (form + admin adjust + LD transfer) per month, so the
     * admin can see which group deposits most each month. Dummy accounts left
     * out. Sorted by group total, biggest first.
     */
    public function groupDeposits(Request $request): StreamedResponse
    {
        $parent = User::whereRaw('LOWER(username) = ?', [strtolower(trim((string) $request->query('parent', '')))])->first();
        abort_unless($parent, 404, 'Parent member not found.');

        $dummy   = User::where('is_dummy', true)->orWhere('exclude_from_stats', true)->pluck('id')->all();
        $leaders = User::where('sponsor_id', $parent->id)->orderBy('created_at')->get(['id', 'username']);

        $allMonths = [];
        $data = [];
        foreach ($leaders as $leader) {
            $ids     = array_values(array_diff($this->downlineIds($leader->id), $dummy));
            $monthly = $this->groupMonthlyTotals($ids);
            foreach (array_keys($monthly) as $ym) { $allMonths[$ym] = true; }
            $data[] = ['leader' => $leader->username, 'members' => count($ids), 'months' => $monthly, 'total' => array_sum($monthly)];
        }
        ksort($allMonths);
        $months = array_keys($allMonths);
        usort($data, fn ($a, $b) => $b['total'] <=> $a['total']);

        $headers = array_merge(['Group Leader', 'Ahli'], $months, ['Jumlah (USDT)']);
        $rows = [];
        $colTotals = array_fill_keys($months, 0.0); $grand = 0.0; $totalMembers = 0;
        foreach ($data as $d) {
            $row = [$d['leader'], $d['members']];
            foreach ($months as $ym) {
                $v = (float) ($d['months'][$ym] ?? 0);
                $row[] = number_format($v, 2, '.', '');
                $colTotals[$ym] += $v;
            }
            $row[] = number_format($d['total'], 2, '.', '');
            $rows[] = $row;
            $grand += $d['total']; $totalMembers += $d['members'];
        }
        $totalRow = ['TOTAL', $totalMembers];
        foreach ($months as $ym) { $totalRow[] = number_format($colTotals[$ym], 2, '.', ''); }
        $totalRow[] = number_format($grand, 2, '.', '');
        $rows[] = $totalRow;

        $filename = 'regal_group_deposits_' . $parent->username . '_' . now()->format('Ymd') . '.csv';
        return response()->streamDownload(function () use ($headers, $rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $headers);
            foreach ($rows as $r) { fputcsv($out, $r); }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * JSON for the printable group-deposit view: each direct downline of the
     * parent is a group, with its deposit broken into form / admin adjust / LD
     * per month, plus an all-time total. Dummy accounts left out.
     */
    public function groupDepositsView(Request $request)
    {
        $parent = User::whereRaw('LOWER(username) = ?', [strtolower(trim((string) $request->query('parent', '')))])->first();
        abort_unless($parent, 404, 'Parent member not found.');

        $dummy   = User::where('is_dummy', true)->orWhere('exclude_from_stats', true)->pluck('id')->all();
        $leaders = User::where('sponsor_id', $parent->id)->orderBy('created_at')->get(['id', 'username']);

        $allMonths = [];
        $groups = [];
        foreach ($leaders as $leader) {
            $ids = array_values(array_diff($this->downlineIds($leader->id), $dummy));
            $bd  = $this->groupMonthlyBreakdown($ids);
            foreach (array_keys($bd) as $ym) { $allMonths[$ym] = true; }
            $tot = ['form' => 0.0, 'adjust' => 0.0, 'ld' => 0.0, 'total' => 0.0];
            foreach ($bd as $m) { $tot['form'] += $m['form']; $tot['adjust'] += $m['adjust']; $tot['ld'] += $m['ld']; $tot['total'] += $m['total']; }
            $groups[] = ['leader' => $leader->username, 'members' => count($ids), 'monthly' => $bd, 'total' => $tot];
        }
        ksort($allMonths);

        return response()->json([
            'parent'       => $parent->username,
            'months'       => array_keys($allMonths),
            'groups'       => $groups,
            'generated_at' => now()->format('Y-m-d H:i'),
        ]);
    }

    /** Per-month form/adjust/ld/total breakdown for a set of users. */
    protected function groupMonthlyBreakdown(array $ids): array
    {
        if (! $ids) return [];
        $off = '+08:00';
        $out = [];
        $ensure = function (string $ym) use (&$out) {
            if (! isset($out[$ym])) $out[$ym] = ['form' => 0.0, 'adjust' => 0.0, 'ld' => 0.0, 'total' => 0.0];
        };

        foreach (DB::table('deposits')->where('status', 'approved')->whereIn('user_id', $ids)
            ->selectRaw("DATE_FORMAT(CONVERT_TZ(COALESCE(approved_at, created_at), '+00:00', ?), '%Y-%m') ym, COALESCE(SUM(amount),0) s", [$off])
            ->groupBy('ym')->get() as $r) { $ensure($r->ym); $out[$r->ym]['form'] = (float) $r->s; }

        $map = ['admin_adjust' => 'adjust', 'ld_transfer' => 'ld'];
        foreach ($map as $type => $key) {
            foreach (DB::table('wallet_transactions')->where('type', $type)->where('direction', 'credit')->where('wallet_type', 'A')->whereIn('user_id', $ids)
                ->selectRaw("DATE_FORMAT(CONVERT_TZ(created_at, '+00:00', ?), '%Y-%m') ym, COALESCE(SUM(amount),0) s", [$off])
                ->groupBy('ym')->get() as $r) { $ensure($r->ym); $out[$r->ym][$key] = (float) $r->s; }
        }

        foreach ($out as $ym => $m) { $out[$ym]['total'] = $m['form'] + $m['adjust'] + $m['ld']; }
        ksort($out);
        return $out;
    }

    /** All user ids in a member's group: the member + their entire downline. */
    protected function downlineIds(int $rootId): array
    {
        $all = [$rootId];
        $frontier = [$rootId];
        while ($frontier) {
            $kids = User::whereIn('sponsor_id', $frontier)->pluck('id')->all();
            $kids = array_values(array_diff($kids, $all));
            if (! $kids) break;
            $all = array_merge($all, $kids);
            $frontier = $kids;
        }
        return $all;
    }

    /** Combined deposit total (form + admin adjust + LD transfer) per month for a set of users. */
    protected function groupMonthlyTotals(array $ids): array
    {
        if (! $ids) return [];
        $off = '+08:00';
        $months = [];

        $add = function ($rows) use (&$months) {
            foreach ($rows as $r) { $months[$r->ym] = ($months[$r->ym] ?? 0) + (float) $r->s; }
        };

        $add(DB::table('deposits')->where('status', 'approved')->whereIn('user_id', $ids)
            ->selectRaw("DATE_FORMAT(CONVERT_TZ(COALESCE(approved_at, created_at), '+00:00', ?), '%Y-%m') ym, COALESCE(SUM(amount),0) s", [$off])
            ->groupBy('ym')->get());

        foreach (['admin_adjust', 'ld_transfer'] as $type) {
            $add(DB::table('wallet_transactions')->where('type', $type)->where('direction', 'credit')->where('wallet_type', 'A')->whereIn('user_id', $ids)
                ->selectRaw("DATE_FORMAT(CONVERT_TZ(created_at, '+00:00', ?), '%Y-%m') ym, COALESCE(SUM(amount),0) s", [$off])
                ->groupBy('ym')->get());
        }
        ksort($months);
        return $months;
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
