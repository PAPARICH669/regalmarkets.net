<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Rank extends Model
{
    protected $guarded = [];

    protected $casts = [
        'level'              => 'integer',
        'match_percent'      => 'decimal:2',
        'min_fund'           => 'decimal:8',
        'direct_min_deposit' => 'decimal:8',
        'directs_required'   => 'integer',
        'produce_count'      => 'integer',
    ];

    public static function byName(string $name): ?self
    {
        return static::where('name', $name)->first();
    }
}
