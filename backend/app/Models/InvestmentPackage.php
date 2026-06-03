<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InvestmentPackage extends Model
{
    protected $guarded = [];

    protected $casts = [
        'principal'         => 'decimal:8',
        'total_return'      => 'decimal:8',
        'total_paid'        => 'decimal:8',
        'daily_roi_percent' => 'decimal:2',
        'daily_amount'      => 'decimal:8',
        'activated_at'      => 'datetime',
        'completed_at'      => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function roiLogs(): HasMany
    {
        return $this->hasMany(RoiLog::class);
    }

    public function remaining(): string
    {
        return bcsub($this->total_return, $this->total_paid, 8);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
