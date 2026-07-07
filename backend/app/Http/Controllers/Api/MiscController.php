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
    /** The 30 countries Regal Markets accepts for KYC (ISO-3166 alpha-2). */
    public const KYC_COUNTRIES = [
        'MY', 'SG', 'ID', 'TH', 'PH', 'VN', 'BN', 'KH', 'MM', 'LA',
        'AU', 'BD', 'CA', 'CN', 'FR', 'DE', 'HK', 'IN', 'JP', 'NP',
        'NZ', 'PK', 'SA', 'KR', 'LK', 'TW', 'AE', 'GB', 'US',
    ];

    public function ranks()
    {
        return Rank::orderBy('level')->get();
    }

    /**
     * Top 5 SPONSOR leaderboard — ranks members by the NEW INVEST their DIRECT
     * (level-1) downlines funded DURING THE CURRENT MONTH (package activations).
     * Resets at the start of each month, matching the monthly Top-5 reward
     * (rewards:top-sponsors / SponsorRewardService). Computed live (cached briefly).
     */
    public function leaderboard()
    {
        $tz  = config('app.timezone');
        $key = 'leaderboard.monthinvest.' . now($tz)->format('Y-m');

        $payload = \Illuminate\Support\Facades\Cache::remember($key, 300, function () use ($tz) {
            $start = now($tz)->startOfMonth();
            $end   = now($tz)->endOfMonth();

            // New invest per member THIS month (from package activations).
            $investByUser = \App\Models\InvestmentPackage::whereBetween('activated_at', [$start, $end])
                ->selectRaw('user_id, SUM(principal) as inv')
                ->groupBy('user_id')
                ->pluck('inv', 'user_id');

            $sponsorOf = \App\Models\User::pluck('sponsor_id', 'id');

            // Attribute each member's monthly invest to their DIRECT sponsor.
            $group = [];
            foreach ($investByUser as $uid => $inv) {
                $sponsor = (int) ($sponsorOf[$uid] ?? 0);
                if ($sponsor && (float) $inv > 0) {
                    $group[$sponsor] = ($group[$sponsor] ?? 0) + (float) $inv;
                }
            }

            // Drop admins/staff, then take the top 5.
            $adminIds = \App\Models\User::where('is_admin', true)->orWhere('is_staff', true)->pluck('id');
            foreach ($adminIds as $id) {
                unset($group[$id]);
            }
            arsort($group);
            $top = array_slice($group, 0, 5, true);

            $users = \App\Models\User::whereIn('id', array_keys($top))
                ->with('rank:id,name')->get()->keyBy('id');

            $pos = 0; $out = [];
            foreach ($top as $uid => $amount) {
                $u = $users[$uid] ?? null;
                $out[] = [
                    'position' => ++$pos,
                    'name'     => $u?->nickname ?: ($u?->username ?? 'Member'),
                    'rank'     => $u?->rankName() ?? 'USER',
                    'sales'    => (float) $amount,
                ];
            }
            return ['top' => $out];
        });

        return response()->json($payload);
    }

    /**
     * Monthly profit series for the member dashboard bar chart. Each month's
     * profit % = daily ROI rate × the number of distinct days ROI was distributed
     * that month. Starts Jun 2026; the current month grows daily as ROI runs.
     */
    public function monthlyProfit()
    {
        $tz  = config('app.timezone');
        $key = 'dashboard.monthlyprofit.' . now($tz)->format('Y-m');

        $payload = \Illuminate\Support\Facades\Cache::remember($key, 300, function () use ($tz) {
            // Actual profit % per month = SUM of the REAL daily rates that were paid
            // (each day's rate = roi amount / package principal), NOT days × current
            // rate — the admin can change the daily rate over time, so past months must
            // reflect what was actually distributed, not today's rate.
            $pctByMonth = collect(\Illuminate\Support\Facades\DB::select(
                "SELECT DATE_FORMAT(d.roi_date, '%Y-%m') AS ym, SUM(d.daily_rate) AS pct
                 FROM (
                     SELECT r.roi_date, AVG(r.amount / NULLIF(p.principal, 0)) * 100 AS daily_rate
                     FROM roi_logs r
                     JOIN investment_packages p ON p.id = r.investment_package_id
                     GROUP BY r.roi_date
                 ) d
                 GROUP BY DATE_FORMAT(d.roi_date, '%Y-%m')"
            ))->pluck('pct', 'ym');

            // Fixed range: Jun 2026 → Dec 2026. Future months show as empty bars.
            $start = \Carbon\Carbon::createFromFormat('Y-m', '2026-06', $tz)->startOfMonth();
            $end   = \Carbon\Carbon::createFromFormat('Y-m', '2026-12', $tz)->startOfMonth();
            $now   = now($tz)->startOfMonth();
            $curYm = $now->format('Y-m');

            $months = [];
            $total  = 0.0;
            for ($m = $start->copy(); $m->lessThanOrEqualTo($end); $m->addMonthNoOverflow()) {
                $ym  = $m->format('Y-m');
                $val = round((float) ($pctByMonth[$ym] ?? 0), 2);
                $total += $val;
                $months[] = [
                    'label'   => $m->format('M y'),
                    'value'   => $val,
                    'current' => $ym === $curYm,
                    'future'  => $m->greaterThan($now),
                ];
            }

            // Platform-wide total profit % paid since inception — identical for every member.
            return ['months' => $months, 'total' => round($total, 2), 'since' => 'Jun 2026'];
        });

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
            // Auto-deposit is live when enabled (public BSC RPC needs no key).
            'deposit_auto_verify'    => (bool) config('regal.deposit.auto_verify'),
        ]);
    }

    public function updateProfile(Request $request)
    {
        // Members may edit nickname and heir details only.
        // Email, phone and the USDT withdrawal address are NOT editable here
        // (admin-only) — the wallet address is locked for payout security.
        $data = $request->validate([
            'nickname'       => ['nullable', 'string', 'max:30'],
            'heir_name'      => ['nullable', 'string', 'max:120'],
            'heir_phone'     => ['nullable', 'string', 'max:30'],
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
            'kyc_country' => ['required', 'string', 'size:2', \Illuminate\Validation\Rule::in(self::KYC_COUNTRIES)],
            'id_type'     => ['required', 'in:ic,passport,license'],
            // id_number must be unique across users (no duplicate IC/passport/license).
            'id_number'   => ['required', 'string', 'max:60', \Illuminate\Validation\Rule::unique('users', 'id_number')->ignore($user->id)],
            'document'    => ['required', 'file', 'mimes:jpg,jpeg,png,webp,heic,heif,pdf', 'max:20480'],
            // Plain selfie of the member's face (image only) — staff face-check vs the ID photo.
            'selfie'      => ['required', 'file', 'mimes:jpg,jpeg,png,webp,heic,heif', 'max:20480'],
        ]);

        $country = strtoupper($data['kyc_country']);

        // AUTO-REJECT: the ID number must match the real format for its country/type.
        $formatError = \App\Services\KycIdValidator::validate($country, $data['id_type'], $data['id_number']);
        if ($formatError !== null) {
            throw \Illuminate\Validation\ValidationException::withMessages(['id_number' => $formatError]);
        }

        // AUTO-REJECT duplicates: reject a document OR selfie already used by another account.
        $docHash    = hash_file('sha256', $request->file('document')->getRealPath());
        $selfieHash = hash_file('sha256', $request->file('selfie')->getRealPath());

        if (\App\Models\User::where('kyc_document_hash', $docHash)->where('id', '!=', $user->id)->exists()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'document' => 'This document has already been used by another account.',
            ]);
        }
        if (\App\Models\User::where('kyc_selfie_hash', $selfieHash)->where('id', '!=', $user->id)->exists()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'selfie' => 'This selfie has already been used by another account.',
            ]);
        }

        $disk = config('regal.proof_disk', 'local');
        $docPath    = $request->file('document')->store('kyc', $disk);
        $selfiePath = $request->file('selfie')->store('kyc', $disk);

        // Stamp "REGAL MARKETS · @user · date" over both images so a leaked copy
        // can never be reused as a genuine document elsewhere. (PDFs are skipped.)
        $label = "REGAL MARKETS\n@{$user->username}\n" . now()->format('Y-m-d');
        foreach ([$docPath, $selfiePath] as $stored) {
            try {
                \App\Services\ImageWatermark::stamp(\Illuminate\Support\Facades\Storage::disk($disk)->path($stored), $label);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('KYC watermark skipped: ' . $e->getMessage());
            }
        }

        $user->update([
            'kyc_country'       => $country,
            'id_type'           => $data['id_type'],
            'id_number'         => $data['id_number'],
            'kyc_document_path' => $docPath,
            'kyc_document_hash' => $docHash,
            'kyc_selfie_path'   => $selfiePath,
            'kyc_selfie_hash'   => $selfieHash,
            'kyc_status'        => 'pending',
            'kyc_note'          => null,
        ]);

        app(\App\Services\TelegramService::class)->notify('🪪 KYC Submitted', [
            'User'    => '@' . $user->username,
            'Country' => $country,
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
