<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Withdrawal extends Model
{
    protected $guarded = [];

    protected $casts = [
        'amount'             => 'decimal:8',
        'fee'                => 'decimal:8',
        'net_amount'         => 'decimal:8',
        'system_rate'        => 'decimal:8',
        'coin_fee'           => 'decimal:12',
        'coin_amount_est'    => 'decimal:12',
        'coin_amount_actual' => 'decimal:12',
        'processed_at'       => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }
}
