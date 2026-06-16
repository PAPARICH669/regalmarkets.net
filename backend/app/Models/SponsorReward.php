<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SponsorReward extends Model
{
    protected $fillable = [
        'period', 'user_id', 'position', 'score', 'amount', 'status', 'paid_by', 'paid_at',
    ];

    protected $casts = [
        'score'   => 'decimal:8',
        'amount'  => 'decimal:8',
        'paid_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
