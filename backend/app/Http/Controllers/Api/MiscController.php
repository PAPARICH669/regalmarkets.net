<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\Rank;
use App\Models\WalletTransaction;
use App\Services\MaintenanceService;
use App\Services\SettingsService;
use Illuminate\Http\Request;

class MiscController extends Controller
{
    public function ranks()
    {
        return Rank::orderBy('level')->get();
    }

    public function announcements()
    {
        return Announcement::active()->latest('published_at')->take(20)->get();
    }

    public function maintenanceStatus(MaintenanceService $maintenance)
    {
        return response()->json($maintenance->status());
    }

    public function walletTransactions(Request $request)
    {
        $q = WalletTransaction::where('user_id', $request->user()->id);
        if ($type = $request->query('wallet')) {
            $q->where('wallet_type', $type);
        }
        return $q->latest()->paginate(20);
    }

    public function publicSettings(SettingsService $settings)
    {
        // Non-sensitive settings exposed to the frontend (plans, percentages, limits).
        return response()->json([
            'roi_daily_percent'      => (float) $settings->get('roi_daily_percent'),
            'roi_return_multiple'    => (float) $settings->get('roi_return_multiple'),
            'min_deposit'            => (float) $settings->get('min_deposit'),
            'min_withdrawal'         => (float) $settings->get('min_withdrawal'),
            'max_withdrawal_daily'   => (float) $settings->get('max_withdrawal_daily'),
            'withdrawal_fee_percent' => (float) $settings->get('withdrawal_fee_percent'),
            'sponsor_bonus_percents' => $settings->get('sponsor_bonus_percents'),
            'match_percents'         => $settings->get('match_percents'),
        ]);
    }

    public function updateProfile(Request $request)
    {
        $data = $request->validate([
            'wallet_address' => ['nullable', 'string', 'max:120'],
        ]);
        $request->user()->update($data);
        return response()->json(['message' => 'Profile updated.']);
    }
}
