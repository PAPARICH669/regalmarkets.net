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
            'password'       => ['required', 'string', 'min:6', 'confirmed'],
            'referral_code'  => ['nullable', 'string', 'exists:users,referral_code'],
            'wallet_address' => ['nullable', 'string', 'max:120'],
        ]);

        $sponsor = ! empty($data['referral_code'])
            ? User::where('referral_code', $data['referral_code'])->first()
            : null;

        $userRank = Rank::byName('USER');

        $user = User::create([
            'username'       => $data['username'],
            'name'           => $data['username'],
            'email'          => $data['email'],
            'password'       => Hash::make($data['password']),
            'sponsor_id'     => $sponsor?->id,
            'rank_id'        => $userRank?->id,
            'referral_code'  => $this->uniqueReferralCode(),
            'wallet_address' => $data['wallet_address'] ?? null,
        ]);

        // Provision both wallets
        Wallet::create(['user_id' => $user->id, 'type' => 'A', 'balance' => 0]);
        Wallet::create(['user_id' => $user->id, 'type' => 'E', 'balance' => 0]);

        $token = $user->createToken('api')->plainTextToken;

        return response()->json([
            'message' => 'Registration successful.',
            'token'   => $token,
            'user'    => $this->userPayload($user->fresh(['rank', 'wallets'])),
        ], 201);
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'login'    => ['required', 'string'], // username or email
            'password' => ['required', 'string'],
        ]);

        $user = User::where('username', $data['login'])
            ->orWhere('email', $data['login'])
            ->first();

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
            throw ValidationException::withMessages(['login' => 'Invalid credentials.']);
        }

        if ($user->is_frozen) {
            throw ValidationException::withMessages(['login' => 'Your account is frozen. Contact support.']);
        }

        $user->update(['last_login_ip' => $request->ip(), 'last_login_at' => now()]);

        $token = $user->createToken('api')->plainTextToken;

        return response()->json([
            'message' => 'Login successful.',
            'token'   => $token,
            'user'    => $this->userPayload($user->fresh(['rank', 'wallets'])),
        ]);
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
        \Illuminate\Support\Facades\Password::sendResetLink($request->only('email'));

        // Always generic to avoid email enumeration.
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
            'email'          => $user->email,
            'rank'           => $user->rankName(),
            'rank_level'     => $user->rank?->level ?? 1,
            'referral_code'  => $user->referral_code,
            'sponsor'        => $user->sponsor?->username,
            'wallet_address' => $user->wallet_address,
            'is_admin'       => $user->is_admin,
            'is_frozen'      => $user->is_frozen,
            'total_invested' => (float) $user->total_invested,
            'total_fund'     => (float) $user->total_fund,
            'wallet_a'       => (float) $user->walletBalance('A'),
            'wallet_e'       => (float) $user->walletBalance('E'),
            'two_factor_enabled' => $user->two_factor_enabled,
        ];
    }
}
