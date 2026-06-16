<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountChangeRequest extends Model
{
    protected $fillable = [
        'user_id', 'field', 'old_value', 'new_value', 'status',
        'tac_code', 'tac_expires_at', 'tac_verified_at',
        'processed_by', 'processed_at', 'note',
    ];

    protected $hidden = ['tac_code'];

    protected $casts = [
        'tac_expires_at'  => 'datetime',
        'tac_verified_at' => 'datetime',
        'processed_at'    => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
