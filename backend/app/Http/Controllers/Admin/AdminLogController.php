<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\MatchingBonusLog;
use App\Models\SponsorBonusLog;

class AdminLogController extends Controller
{
    public function matching()
    {
        return MatchingBonusLog::with(['fromUser:id,username', 'toUser:id,username'])->latest()->paginate(30);
    }

    public function sponsor()
    {
        return SponsorBonusLog::with(['fromUser:id,username', 'toUser:id,username'])->latest()->paginate(30);
    }

    public function audit()
    {
        return AuditLog::with('user:id,username')->latest()->paginate(30);
    }
}
