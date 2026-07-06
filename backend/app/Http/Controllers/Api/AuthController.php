<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LoginHistory;
use App\Models\Rank;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $data = $request->validate([
            'username'       => ['required', 'string', 'min:3', 'max:30', 'alpha_dash', 'unique:users,username'],
            'email'          => ['required', 'email', 'unique:users,email'],
            'phone'          => ['required', 'string', 'max:30', 'unique:users,phone'],
            'country'        => ['nullable', 'string', 'size:2'],
            'password'       => ['required', 'string', 'min:6', 'confirmed'],
            'referral_code'  => ['nullable', 'string', 'max:60'],
            'wallet_address' => ['nullable', 'string', 'max:120'],
        ]);

        // Resolve sponsor by USERNAME or referral code (the referral link uses the
        // sponsor's username so new members don't pick the wrong sponsor).
        $sponsor = null;
        if (! empty($data['referral_code'])) {
            $ref = trim($data['referral_code']);
            $sponsor = User::where('username', $ref)->orWhere('referral_code', $ref)->first();
            if (! $sponsor) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'referral_code' => 'Sponsor not found. Please check the referral link.',
                ]);
            }
        }

        $userRank = Rank::byName('USER');
        $code = (string) random_int(100000, 999999);

        $user = User::create([
            'username'                => $data['username'],
            'name'                    => $data['username'],
            'nickname'                => $data['username'],
            'email'                   => $data['email'],
            'phone'                   => $data['phone'],
            'country'                 => strtoupper($data['country'] ?? '') ?: null,
            'password'                => Hash::make($data['password']),
            'sponsor_id'              => $sponsor?->id,
            'rank_id'                 => $userRank?->id,
            'referral_code'           => $this->uniqueReferralCode(),
            'wallet_address'          => $data['wallet_address'] ?? null,
            'email_verification_code' => $code,
            'email_verified_at'       => null,
        ]);

        // Provision both wallets
        Wallet::create(['user_id' => $user->id, 'type' => 'A', 'balance' => 0]);
        Wallet::create(['user_id' => $user->id, 'type' => 'E', 'balance' => 0]);

        $this->sendVerificationEmail($user, $code);

        app(\App\Services\TelegramService::class)->notify('🆕 New Registration', [
            'Username' => $user->username,
            'Email'    => $user->email,
            'Phone'    => $user->phone,
            'Sponsor'  => $sponsor?->username ?? '—',
        ]);

        // No token until the email is verified.
        return response()->json([
            'message'             => 'Registration successful. Enter the 6-digit code sent to your email to activate your account.',
            'needs_verification'  => true,
            'email'               => $user->email,
        ], 201);
    }

    public function verifyEmail(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'exists:users,email'],
            'code'  => ['required', 'string'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if ($user->email_verified_at) {
            // already verified
        } elseif (! $user->email_verification_code || ! hash_equals($user->email_verification_code, trim($data['code']))) {
            throw ValidationException::withMessages(['code' => 'Invalid verification code.']);
        }

        $user->update(['email_verified_at' => now(), 'email_verification_code' => null]);
        $user->update(['last_login_ip' => $request->ip(), 'last_login_at' => now()]);

        return response()->json([
            'message' => 'Email verified. Welcome to Regal Markets!',
            'token'   => $user->createToken('api')->plainTextToken,
            'user'    => $this->userPayload($user->fresh(['rank', 'wallets'])),
        ]);
    }

    public function resendCode(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'email', 'exists:users,email']]);
        $user = User::where('email', $data['email'])->first();
        if (! $user->email_verified_at) {
            $code = (string) random_int(100000, 999999);
            $user->update(['email_verification_code' => $code]);
            $this->sendVerificationEmail($user, $code);
        }
        return response()->json(['message' => 'If the account is unverified, a new code has been sent.']);
    }

    protected function sendVerificationEmail(User $user, string $code): void
    {
        try {
            $html = '<div style="font-family:Arial,sans-serif;background:#04102a;padding:32px;color:#eef3fc">'
                . '<div style="max-width:520px;margin:auto;background:#0a1c40;border:1px solid rgba(201,162,39,.3);border-radius:14px;padding:28px">'
                . '<h2 style="color:#e7c873;margin:0 0 8px">Regal Markets</h2>'
                . '<p style="margin:0 0 16px;color:#9fb1d4">Your email verification code is:</p>'
                . '<p style="font-size:34px;font-weight:bold;letter-spacing:8px;color:#e7c873;margin:0 0 16px">' . $code . '</p>'
                . '<p style="margin:0;color:#9fb1d4;font-size:12px">Enter this code to activate your account. If you did not register, ignore this email.</p>'
                . '</div></div>';
            app(\App\Services\MailService::class)->send($user->email, $user->username, 'Your Regal Markets verification code', $html);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Verification email failed: ' . $e->getMessage());
        }
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $data['email'])->first();

        $ok = $user && Hash::check($data['password'], $user->password);

        if ($user) {
            LoginHistory::create([
                'user_id'    => $user->id,
                'ip'         => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 255),
                'successful' => $ok,
            ]);
        }

        if (! $ok) {
            throw ValidationException::withMessages(['email' => 'Invalid email or password.']);
        }

        // Members cannot log in during the maintenance window; admins always can.
        if (! $user->is_admin && app(\App\Services\MaintenanceService::class)->isActive()) {
            return response()->json([
                'message'     => 'System is under maintenance. Login re-opens at the end of the window.',
                'maintenance' => app(\App\Services\MaintenanceService::class)->status(),
            ], 503);
        }

        if (! $user->email_verified_at) {
            // Re-send a fresh code and tell the frontend to show the verify screen.
            $code = (string) random_int(100000, 999999);
            $user->update(['email_verification_code' => $code]);
            $this->sendVerificationEmail($user, $code);
            return response()->json([
                'message'            => 'Please verify your email. A new code has been sent.',
                'needs_verification' => true,
                'email'              => $user->email,
            ], 403);
        }

        if ($user->is_frozen) {
            throw ValidationException::withMessages(['email' => 'Your account is frozen. Contact support.']);
        }

        // ----- Two-step login: email TAC (OTP) -----
        // Applies to ADMIN and STAFF accounts only — regular members log in
        // directly. When enabled, a 6-digit code is emailed and the caller must
        // confirm it via /login/verify-tac before a token is issued. If the email
        // cannot be sent (mail outage / misconfig), we fall back to a normal login
        // so nobody gets locked out.
        if (($user->is_admin || $user->is_staff)
            && (bool) app(\App\Services\SettingsService::class)->get('login_tac_enabled', true)) {
            $code = (string) random_int(100000, 999999);
            $user->update([
                'login_tac_code'       => $code,
                'login_tac_expires_at' => now()->addMinutes(10),
            ]);
            if ($this->sendTacEmail($user, $code)) {
                return response()->json([
                    'message'      => 'A 6-digit login code (TAC) has been sent to your email.',
                    'tac_required' => true,
                    'email'        => $user->email,
                ]);
            }
            // Email unavailable -> clear the pending code and continue normal login.
            $user->update(['login_tac_code' => null, 'login_tac_expires_at' => null]);
            \Illuminate\Support\Facades\Log::warning("Login TAC email failed for {$user->email}; fell back to direct login.");
        }

        return $this->issueLogin($user, $request);
    }

    /**
     * Step 2 of TAC login: verify the emailed code and issue the API token.
     */
    public function verifyTac(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'code'  => ['required', 'string'],
        ]);

        $user = User::where('email', $data['email'])->first();

        $valid = $user
            && $user->login_tac_code
            && $user->login_tac_expires_at
            && now()->lessThanOrEqualTo($user->login_tac_expires_at)
            && hash_equals($user->login_tac_code, trim($data['code']));

        if (! $valid) {
            throw ValidationException::withMessages(['code' => 'Invalid or expired login code.']);
        }

        // Re-check gates that could have changed since password step.
        if (! $user->is_admin && app(\App\Services\MaintenanceService::class)->isActive()) {
            return response()->json([
                'message'     => 'System is under maintenance. Login re-opens at the end of the window.',
                'maintenance' => app(\App\Services\MaintenanceService::class)->status(),
            ], 503);
        }
        if ($user->is_frozen) {
            throw ValidationException::withMessages(['email' => 'Your account is frozen. Contact support.']);
        }

        $user->update(['login_tac_code' => null, 'login_tac_expires_at' => null]);

        return $this->issueLogin($user, $request);
    }

    /** Re-send a fresh login TAC. */
    public function resendTac(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'email']]);
        $user = User::where('email', $data['email'])->first();
        if ($user) {
            $code = (string) random_int(100000, 999999);
            $user->update(['login_tac_code' => $code, 'login_tac_expires_at' => now()->addMinutes(10)]);
            $this->sendTacEmail($user, $code);
        }
        return response()->json(['message' => 'If the account exists, a new login code has been sent.']);
    }

    /** Finalize a successful login: stamp last-login and mint the token. */
    protected function issueLogin(User $user, Request $request)
    {
        $user->update(['last_login_ip' => $request->ip(), 'last_login_at' => now()]);

        return response()->json([
            'message' => 'Login successful.',
            'token'   => $user->createToken('api')->plainTextToken,
            'user'    => $this->userPayload($user->fresh(['rank', 'wallets'])),
        ]);
    }

    /** Email a login TAC. Returns true only if the mail provider accepted it. */
    protected function sendTacEmail(User $user, string $code): bool
    {
        try {
            $html = '<div style="font-family:Arial,sans-serif;background:#04102a;padding:32px;color:#eef3fc">'
                . '<div style="max-width:520px;margin:auto;background:#0a1c40;border:1px solid rgba(201,162,39,.3);border-radius:14px;padding:28px">'
                . '<h2 style="color:#e7c873;margin:0 0 8px">Regal Markets</h2>'
                . '<p style="margin:0 0 16px;color:#9fb1d4">Your login verification code (TAC) is:</p>'
                . '<p style="font-size:34px;font-weight:bold;letter-spacing:8px;color:#e7c873;margin:0 0 16px">' . $code . '</p>'
                . '<p style="margin:0;color:#9fb1d4;font-size:12px">This code expires in 10 minutes. If you did not try to log in, change your password immediately.</p>'
                . '</div></div>';
            return app(\App\Services\MailService::class)->send($user->email, $user->username, 'Your Regal Markets login code (TAC)', $html);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Login TAC email failed: ' . $e->getMessage());
            return false;
        }
    }

    public function me(Request $request)
    {
        return $this->userPayload($request->user()->fresh(['rank', 'wallets', 'sponsor']));
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()?->delete();
        return response()->json(['message' => 'Logged out.']);
    }

    public function forgotPassword(Request $request)
    {
        $request->validate(['email' => ['required', 'email']]);

        // Token-based reset via Laravel's Password broker. The email link points
        // to the frontend (see AuthServiceProvider::boot ResetPassword::createUrlUsing).
        // Never surface mail/transport errors to the client (avoid 500 + enumeration).
        try {
            \Illuminate\Support\Facades\Password::sendResetLink($request->only('email'));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Password reset email failed: ' . $e->getMessage());
        }

        return response()->json(['message' => 'If that email is registered, a password reset link has been sent.']);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'token'    => ['required', 'string'],
            'email'    => ['required', 'email'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ]);

        $status = \Illuminate\Support\Facades\Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill(['password' => Hash::make($password)])->save();
                $user->tokens()->delete(); // revoke API tokens after reset
            }
        );

        if ($status !== \Illuminate\Support\Facades\Password::PASSWORD_RESET) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        }

        return response()->json(['message' => 'Password has been reset. You can now log in.']);
    }

    protected function uniqueReferralCode(): string
    {
        do {
            $code = strtoupper(Str::random(8));
        } while (User::where('referral_code', $code)->exists());
        return $code;
    }

    protected function userPayload(User $user): array
    {
        return [
            'id'             => $user->id,
            'username'       => $user->username,
            'name'           => $user->name ?: $user->username,
            'nickname'       => $user->nickname ?: $user->username,
            'email'          => $user->email,
            'phone'          => $user->phone,
            'country'        => $user->country,
            'rank'           => $user->rankName(),
            'rank_level'     => $user->rank?->level ?? 1,
            'referral_code'  => $user->referral_code,
            'sponsor'        => $user->sponsor?->username,
            'wallet_address' => $user->wallet_address,
            'heir_name'      => $user->heir_name,
            'heir_phone'     => $user->heir_phone,
            'id_type'        => $user->id_type,
            'id_number'      => $user->id_number,
            'kyc_status'     => $user->kyc_status ?? 'unsubmitted',
            'kyc_note'       => $user->kyc_note,
            'email_verified' => (bool) $user->email_verified_at,
            'is_admin'       => $user->is_admin,
            'is_staff'       => $user->is_staff,
            'can_kyc'        => $user->can_kyc,
            'can_cr'         => $user->can_cr,
            'is_ld'          => $user->is_ld,
            'is_frozen'      => $user->is_frozen,
            'total_invested' => (float) $user->total_invested,
            'total_fund'     => (float) $user->total_fund,
            'wallet_a'       => (float) $user->walletBalance('A'),
            'wallet_e'       => (float) $user->walletBalance('E'),
            'wallet_l'       => (float) $user->walletBalance('L'),
            'two_factor_enabled' => $user->two_factor_enabled,
            'created_at'     => $user->created_at?->toIso8601String(),
        ];
    }
}
