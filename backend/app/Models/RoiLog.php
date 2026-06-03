<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoiLog extends Model
{
    protected $guarded = [];

    protected $casts = [
        'amount'   => 'decimal:8',
        'roi_date' => 'date',
    ];

    public function package(): BelongsTo
    {
        return $this->belongsTo(InvestmentPackage::class, 'investment_package_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
