<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class AuditService
{
    public function log(Request $request, string $action, ?Model $subject = null, array $meta = []): AuditLog
    {
        return AuditLog::create([
            'user_id'      => $request->user()?->id,
            'action'       => $action,
            'subject_type' => $subject ? get_class($subject) : null,
            'subject_id'   => $subject?->getKey(),
            'meta'         => $meta ?: null,
            'ip'           => $request->ip(),
        ]);
    }
}
