<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'username', 'name', 'nickname', 'email', 'phone', 'country', 'password', 'sponsor_id', 'rank_id',
        'referral_code', 'total_invested', 'total_fund', 'wallet_address',
        'btc_address', 'eth_address', 'sol_address', 'btc_native_address', 'sol_native_address',
        'heir_name', 'heir_phone',
        'id_type', 'id_number', 'kyc_country', 'kyc_document_path', 'kyc_document_hash',
        'kyc_selfie_path', 'kyc_selfie_hash', 'kyc_status', 'kyc_note',
        'kyc_verified_at', 'kyc_verified_by', 'email_verification_code', 'email_verified_at',
        'login_tac_code', 'login_tac_expires_at',
        'is_admin', 'is_staff', 'can_kyc', 'can_cr', 'is_frozen', 'exclude_from_stats', 'is_dummy', 'is_ld', 'two_factor_secret', 'two_factor_enabled',
        'ld_tac_code', 'ld_tac_expires_at', 'ld_tac_member_id', 'ld_tac_amount',
        'last_login_ip', 'last_login_at',
    ];

    protected $hidden = [
        'password', 'remember_token', 'two_factor_secret', 'email_verification_code', 'login_tac_code', 'ld_tac_code',
        // Never serialize the login IP — protects the admin's/members' location
        // even if a raw User model is ever returned in a response by mistake.
        'last_login_ip',
    ];

    protected $casts = [
        'email_verified_at'  => 'datetime',
        'login_tac_expires_at' => 'datetime',
        'kyc_verified_at'    => 'datetime',
        'last_login_at'      => 'datetime',
        'is_admin'           => 'boolean',
        'is_staff'           => 'boolean',
        'can_kyc'            => 'boolean',
        'can_cr'             => 'boolean',
        'is_frozen'          => 'boolean',
        'exclude_from_stats' => 'boolean',
        'is_dummy'           => 'boolean',
        'is_ld'              => 'boolean',
        'ld_tac_expires_at'  => 'datetime',
        'two_factor_enabled' => 'boolean',
        'total_invested'     => 'decimal:8',
        'total_fund'         => 'decimal:8',
    ];

    // ----- Relationships -----------------------------------------------------

    public function sponsor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sponsor_id');
    }

    public function referrals(): HasMany
    {
        return $this->hasMany(User::class, 'sponsor_id');
    }

    public function rank(): BelongsTo
    {
        return $this->belongsTo(Rank::class);
    }

    public function wallets(): HasMany
    {
        return $this->hasMany(Wallet::class);
    }

    public function walletA(): HasOne
    {
        return $this->hasOne(Wallet::class)->where('type', 'A');
    }

    public function walletE(): HasOne
    {
        return $this->hasOne(Wallet::class)->where('type', 'E');
    }

    public function deposits(): HasMany
    {
        return $this->hasMany(Deposit::class);
    }

    public function packages(): HasMany
    {
        return $this->hasMany(InvestmentPackage::class);
    }

    public function withdrawals(): HasMany
    {
        return $this->hasMany(Withdrawal::class);
    }

    public function rankHistories(): HasMany
    {
        return $this->hasMany(RankHistory::class);
    }

    // ----- Helpers -----------------------------------------------------------

    public function walletBalance(string $type): string
    {
        return $this->wallets->firstWhere('type', $type)?->balance ?? '0';
    }

    public function rankName(): string
    {
        return $this->rank?->name ?? 'USER';
    }

    public function scopeMembers($query)
    {
        return $query->where('is_admin', false);
    }

    /**
     * Send the password reset link via the Brevo HTTP API (the VPS blocks SMTP).
     * Builds a link to the Next.js /reset-password page.
     */
    public function sendPasswordResetNotification($token)
    {
        $base = rtrim(config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:3000')), '/');
        $url  = $base . '/reset-password?token=' . $token . '&email=' . urlencode($this->getEmailForPasswordReset());

        app(\App\Services\MailService::class)->sendPasswordReset($this->email, $this->username, $url);
    }
}
