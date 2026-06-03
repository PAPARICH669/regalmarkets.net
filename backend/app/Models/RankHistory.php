<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RankHistory extends Model
{
    protected $guarded = [];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function fromRank(): BelongsTo
    {
        return $this->belongsTo(Rank::class, 'from_rank_id');
    }

    public function toRank(): BelongsTo
    {
        return $this->belongsTo(Rank::class, 'to_rank_id');
    }
}
