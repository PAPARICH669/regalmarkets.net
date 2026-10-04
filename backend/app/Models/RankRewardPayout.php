<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RankRewardPayout extends Model
{
    protected $guarded = [];

    protected $casts = [
        'amount'  => 'decimal:8',
        'paid_at' => 'datetime',
    ];
}
