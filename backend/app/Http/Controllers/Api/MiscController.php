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
            'deposit_address'        => $settings->get('deposit_address'),
            'deposit_network'        => $settings->get('deposit_network'),
        ]);
    }

    public function updateProfile(Request $request)
    {
        // Members may edit nickname, heir details and their BEP20 wallet address.
        // Email and phone are NOT editable here (admin-only).
        $data = $request->validate([
            'nickname'       => ['nullable', 'string', 'max:30'],
            'heir_name'      => ['nullable', 'string', 'max:120'],
            'heir_phone'     => ['nullable', 'string', 'max:30'],
            'wallet_address' => ['nullable', 'string', 'max:120'],
        ]);
        $request->user()->update($data);
        return response()->json(['message' => 'Profile updated.']);
    }

    public function submitKyc(Request $request)
    {
        $user = $request->user();
        if (($user->kyc_status ?? 'unsubmitted') === 'verified') {
            return response()->json(['message' => 'Your KYC is already verified.'], 422);
        }

        $data = $request->validate([
            'id_type'   => ['required', 'in:ic,passport,license'],
            // id_number must be unique across users (no duplicate IC/passport/license).
            'id_number' => ['required', 'string', 'max:60', \Illuminate\Validation\Rule::unique('users', 'id_number')->ignore($user->id)],
            'document'  => ['required', 'image', 'max:6144'],
        ]);

        $path = $request->file('document')->store('kyc', config('regal.proof_disk', 'local'));

        $user->update([
            'id_type'           => $data['id_type'],
            'id_number'         => $data['id_number'],
            'kyc_document_path' => $path,
            'kyc_status'        => 'pending',
            'kyc_note'          => null,
        ]);

        app(\App\Services\TelegramService::class)->notify('🪪 KYC Submitted', [
            'User'    => '@' . $user->username,
            'ID Type' => strtoupper($data['id_type']),
            'ID No'   => $data['id_number'],
        ]);

        return response()->json(['message' => 'KYC submitted. An admin will review it shortly.']);
    }

    public function changePassword(Request $request)
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password'         => ['required', 'string', 'min:6', 'confirmed'],
        ]);

        $user = $request->user();
        if (! \Illuminate\Support\Facades\Hash::check($data['current_password'], $user->password)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'current_password' => 'Current password is incorrect.',
            ]);
        }

        $user->update(['password' => \Illuminate\Support\Facades\Hash::make($data['password'])]);

        // Invalidate other sessions/tokens for safety, keep the current one.
        $current = $user->currentAccessToken();
        $user->tokens()->where('id', '!=', $current?->id)->delete();

        return response()->json(['message' => 'Password changed successfully.']);
    }
}
