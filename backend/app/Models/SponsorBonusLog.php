<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SponsorBonusLog extends Model
{
    protected $guarded = [];

    protected $casts = [
        'level'   => 'integer',
        'percent' => 'decimal:2',
        'amount'  => 'decimal:8',
    ];

    public function fromUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    public function toUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_user_id');
    }
}
