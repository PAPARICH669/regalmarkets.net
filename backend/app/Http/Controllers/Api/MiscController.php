<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\Deposit;
use App\Models\Rank;
use App\Models\WalletTransaction;
use App\Services\MaintenanceService;
use App\Services\SettingsService;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class MiscController extends Controller
{
    public function ranks()
    {
        return Rank::orderBy('level')->get();
    }

    /**
     * Top 5 GROUP-SALES leaderboard for the CURRENT month. A member's group sales =
     * their own approved deposits + the approved deposits of their entire downline
     * (this month). Computed live (cached briefly), resets each new month.
     */
    public function leaderboard()
    {
        $start   = now()->startOfMonth();
        $payload = \Illuminate\Support\Facades\Cache::remember(
            'leaderboard.' . $start->format('Y-m'),
            300, // refresh at most every 5 minutes
            function () use ($start) {
                // 1) personal approved-deposit volume this month, per user
                $personal = Deposit::where('status', 'approved')
                    ->where('approved_at', '>=', $start)
                    ->select('user_id', DB::raw('SUM(amount) as s'))
                    ->groupBy('user_id')
                    ->pluck('s', 'user_id');

                // 2) sponsor map for the whole tree
                $sponsorOf = \App\Models\User::pluck('sponsor_id', 'id');

                // 3) credit each member's personal sales to their UPLINE only
                //    (start from the sponsor — a member's own sales are excluded;
                //    group sales = entire downline volume, not self)
                $group = [];
                foreach ($personal as $uid => $amt) {
                    $node = (int) ($sponsorOf[$uid] ?? 0); $guard = 0;
                    while ($node && $guard++ < 200) {
                        $group[$node] = ($group[$node] ?? 0) + (float) $amt;
                        $node = (int) ($sponsorOf[$node] ?? 0);
                    }
                }

                // 4) drop admins/staff, then take the top 5
                $adminIds = \App\Models\User::where('is_admin', true)->orWhere('is_staff', true)->pluck('id');
                foreach ($adminIds as $id) {
                    unset($group[$id]);
                }
                arsort($group);
                $top = array_slice($group, 0, 5, true);

                $users = \App\Models\User::whereIn('id', array_keys($top))
                    ->with('rank:id,name')->get()->keyBy('id');

                $pos = 0; $out = [];
                foreach ($top as $uid => $sales) {
                    $u = $users[$uid] ?? null;
                    $out[] = [
                        'position' => ++$pos,
                        'name'     => $u?->nickname ?: ($u?->username ?? 'Member'),
                        'rank'     => $u?->rankName() ?? 'USER',
                        'sales'    => (float) $sales,
                    ];
                }
                return ['month' => now()->format('F Y'), 'top' => $out];
            }
        );

        return response()->json($payload);
    }

    public function announcements()
    {
        return Announcement::active()->latest('published_at')->take(20)->get();
    }

    /** Recent registrations feed (welcome wall): username + country, latest first. */
    public function recentMembers()
    {
        return \App\Models\User::where('is_admin', false)->where('is_staff', false)
            ->whereNotNull('email_verified_at')
            ->latest('created_at')
            ->take(12)
            ->get(['username', 'country', 'created_at'])
            ->map(fn ($u) => [
                'username'   => $u->username,
                'country'    => $u->country,
                'created_at' => $u->created_at?->toIso8601String(),
            ]);
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
            'document'  => ['required', 'file', 'mimes:jpg,jpeg,png,webp,heic,heif,pdf', 'max:8192'],
        ]);

        // Reject a document file that another account has already used (anti-duplicate).
        $hash = hash_file('sha256', $request->file('document')->getRealPath());
        $dupe = \App\Models\User::where('kyc_document_hash', $hash)
            ->where('id', '!=', $user->id)
            ->exists();
        if ($dupe) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'document' => 'This document has already been used by another account.',
            ]);
        }

        $path = $request->file('document')->store('kyc', config('regal.proof_disk', 'local'));

        $user->update([
            'id_type'           => $data['id_type'],
            'id_number'         => $data['id_number'],
            'kyc_document_path' => $path,
            'kyc_document_hash' => $hash,
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
