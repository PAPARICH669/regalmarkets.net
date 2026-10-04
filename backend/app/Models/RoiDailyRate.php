<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One pre-generated daily ROI % per calendar date, used only in the
 * monthly-target ROI mode. See RoiScheduleService.
 */
class RoiDailyRate extends Model
{
    protected $fillable = ['rate_date', 'percent', 'locked'];

    protected $casts = [
        'rate_date' => 'date',
        'percent'   => 'decimal:4',
        'locked'    => 'boolean',
    ];
}
