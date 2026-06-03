<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MatchingBonusLog extends Model
{
    protected $guarded = [];

    protected $casts = [
        'applied_percent' => 'decimal:2',
        'roi_amount'      => 'decimal:8',
        'amount'          => 'decimal:8',
        'depth'           => 'integer',
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
