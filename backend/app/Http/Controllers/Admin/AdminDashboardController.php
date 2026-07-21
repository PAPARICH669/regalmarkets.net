<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Deposit;
use App\Models\InvestmentPackage;
use App\Models\MatchingBonusLog;
use App\Models\SponsorBonusLog;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Models\Withdrawal;
use Illuminate\Support\Facades\DB;

class AdminDashboardController extends Controller
{
    public function index()
    {
        // Dummy/test accounts (e.g. GoldenLine, nizam2b) are flagged and excluded
        // from EVERY aggregate total below — they still work normally for the
        // member. Exclude on either flag so a dummy is never counted.
        $excluded = User::where('is_dummy', true)->orWhere('exclude_from_stats', true)->pluck('id')->all();

        $roiLiability = (float) InvestmentPackage::where('status', 'active')
            ->whereNotIn('user_id', $excluded)
            ->select(DB::raw('COALESCE(SUM(total_return - total_paid),0) as v'))->value('v');

        // Manual deduction from the Total Deposit figure (admin-configurable),
        // e.g. to back out non-deposit seed credits.
        $depositAdjustment = (float) app(\App\Services\SettingsService::class)->get('total_deposit_adjustment', 0);
        $totalDeposits = (float) Deposit::where('status', 'approved')->whereNotIn('user_id', $excluded)->sum('amount')
            + (float) WalletTransaction::where('type', 'admin_adjust')->where('direction', 'credit')->where('wallet_type', 'A')->whereNotIn('user_id', $excluded)->sum('amount')
            - $depositAdjustment;

        return response()->json([
            'members'             => User::members()->whereNotIn('id', $excluded)->count(),
            'frozen_members'      => User::members()->whereNotIn('id', $excluded)->where('is_frozen', true)->count(),
            // Real capital in = approved deposits (form) + manual admin A-WALLET
            // top-ups, minus the configurable deposit adjustment. Dummy excluded.
            'total_deposits'      => $totalDeposits,
            'pending_deposits'    => Deposit::where('status', 'pending')->whereNotIn('user_id', $excluded)->count(),
            'total_withdrawals'   => (float) Withdrawal::where('status', 'approved')->whereNotIn('user_id', $excluded)->sum('amount'),
            'pending_withdrawals' => Withdrawal::where('status', 'pending')->whereNotIn('user_id', $excluded)->count(),
            'roi_liability'       => $roiLiability,
            'total_sponsor_bonus' => (float) SponsorBonusLog::whereNotIn('to_user_id', $excluded)->sum('amount'),
            'total_matching_bonus'=> (float) MatchingBonusLog::whereNotIn('to_user_id', $excluded)->sum('amount'),
            'wallet_a_total'      => (float) Wallet::where('type', 'A')->whereNotIn('user_id', $excluded)->sum('balance'),
            'wallet_e_total'      => (float) Wallet::where('type', 'E')->whereNotIn('user_id', $excluded)->sum('balance'),
            'active_packages'     => InvestmentPackage::where('status', 'active')->whereNotIn('user_id', $excluded)->count(),
        ]);
    }
}
