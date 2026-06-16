<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Deposit;
use App\Models\InvestmentPackage;
use App\Models\MatchingBonusLog;
use App\Models\SponsorBonusLog;
use App\Models\User;
use App\Models\Wallet;
use App\Models\Withdrawal;
use Illuminate\Support\Facades\DB;

class AdminDashboardController extends Controller
{
    public function index()
    {
        // Dummy/test accounts (e.g. GoldenLine) are flagged and excluded from the
        // dashboard aggregate totals below — they still work normally for the member.
        $excluded = User::where('exclude_from_stats', true)->pluck('id')->all();

        $roiLiability = (float) InvestmentPackage::where('status', 'active')
            ->whereNotIn('user_id', $excluded)
            ->select(DB::raw('COALESCE(SUM(total_return - total_paid),0) as v'))->value('v');

        return response()->json([
            'members'             => User::members()->count(),
            'frozen_members'      => User::members()->where('is_frozen', true)->count(),
            'total_deposits'      => (float) Deposit::where('status', 'approved')->sum('amount'),
            'pending_deposits'    => Deposit::where('status', 'pending')->count(),
            'total_withdrawals'   => (float) Withdrawal::where('status', 'approved')->sum('amount'),
            'pending_withdrawals' => Withdrawal::where('status', 'pending')->count(),
            'roi_liability'       => $roiLiability,
            'total_sponsor_bonus' => (float) SponsorBonusLog::whereNotIn('to_user_id', $excluded)->sum('amount'),
            'total_matching_bonus'=> (float) MatchingBonusLog::whereNotIn('to_user_id', $excluded)->sum('amount'),
            'wallet_a_total'      => (float) Wallet::where('type', 'A')->sum('balance'),
            'wallet_e_total'      => (float) Wallet::where('type', 'E')->whereNotIn('user_id', $excluded)->sum('balance'),
            'active_packages'     => InvestmentPackage::where('status', 'active')->count(),
        ]);
    }
}
