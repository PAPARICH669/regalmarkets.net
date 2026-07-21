<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\InvestmentPackage;
use App\Models\User;
use App\Models\Wallet;
use App\Models\Withdrawal;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Per-member financial record for the admin: how much capital each member has
 * locked into packages (modal aktif = total_invested), how much they have
 * withdrawn (approved), and their current A/E wallet balances.
 */
class AdminFinancialController extends Controller
{
    public function index(Request $request)
    {
        [$rows, $totals] = $this->rows($request->query('q'));
        return response()->json(['rows' => $rows, 'totals' => $totals]);
    }

    /** @return array{0:\Illuminate\Support\Collection,1:array} */
    protected function rows(?string $search): array
    {
        $q = User::members();
        if ($search = trim((string) $search)) {
            $q->where(fn ($w) => $w->where('username', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%"));
        }
        $members = $q->orderByDesc('total_invested')->limit(1000)
            ->get(['id', 'username', 'name', 'total_invested', 'is_dummy']);
        $ids = $members->pluck('id');

        $wd = Withdrawal::whereIn('user_id', $ids)->where('status', 'approved')
            ->groupBy('user_id')->selectRaw('user_id, SUM(amount) s')->pluck('s', 'user_id');
        $wA = Wallet::whereIn('user_id', $ids)->where('type', 'A')->pluck('balance', 'user_id');
        $wE = Wallet::whereIn('user_id', $ids)->where('type', 'E')->pluck('balance', 'user_id');

        $rows = $members->map(fn ($u) => [
            'id'        => $u->id,
            'username'  => $u->username,
            'name'      => $u->name ?: $u->username,
            'is_dummy'  => (bool) $u->is_dummy,
            'invested'  => (float) $u->total_invested,
            'withdrawn' => (float) ($wd[$u->id] ?? 0),
            'wallet_a'  => (float) ($wA[$u->id] ?? 0),
            'wallet_e'  => (float) ($wE[$u->id] ?? 0),
        ])->values();

        $totals = [
            'count'     => $rows->count(),
            'invested'  => (float) $rows->sum('invested'),
            'withdrawn' => (float) $rows->sum('withdrawn'),
            'wallet_a'  => (float) $rows->sum('wallet_a'),
            'wallet_e'  => (float) $rows->sum('wallet_e'),
        ];
        return [$rows, $totals];
    }

    /** Drill-down for one member: funded packages + every withdrawal. */
    public function show(User $user)
    {
        return response()->json([
            'member' => [
                'id' => $user->id, 'username' => $user->username, 'name' => $user->name ?: $user->username,
                'total_invested' => (float) $user->total_invested,
                'wallet_a' => (float) optional(Wallet::where('user_id', $user->id)->where('type', 'A')->first())->balance,
                'wallet_e' => (float) optional(Wallet::where('user_id', $user->id)->where('type', 'E')->first())->balance,
            ],
            'packages' => InvestmentPackage::where('user_id', $user->id)->orderByDesc('id')
                ->get(['id', 'principal', 'total_return', 'total_paid', 'status', 'activated_at']),
            'withdrawals' => Withdrawal::where('user_id', $user->id)->latest()
                ->get(['id', 'amount', 'fee', 'net_amount', 'status', 'wallet_address', 'txid', 'created_at', 'processed_at']),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        [$rows] = $this->rows($request->query('q'));
        $headers = ['Username', 'Name', 'Modal Aktif (Invested)', 'Total Withdraw', 'Baki A-Wallet', 'Baki E-Wallet'];
        $filename = 'regal_member_financials_' . now()->format('Ymd') . '.csv';

        return response()->streamDownload(function () use ($headers, $rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $headers);
            foreach ($rows as $r) {
                fputcsv($out, [
                    $r['username'], $r['name'],
                    number_format($r['invested'], 2, '.', ''),
                    number_format($r['withdrawn'], 2, '.', ''),
                    number_format($r['wallet_a'], 2, '.', ''),
                    number_format($r['wallet_e'], 2, '.', ''),
                ]);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
