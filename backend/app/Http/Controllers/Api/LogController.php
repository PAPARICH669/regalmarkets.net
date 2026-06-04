<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MatchingBonusLog;
use App\Models\RoiLog;
use App\Models\SponsorBonusLog;
use Illuminate\Http\Request;

class LogController extends Controller
{
    public function roi(Request $request)
    {
        return RoiLog::where('user_id', $request->user()->id)
            ->with('package:id,principal')->latest()->paginate(20);
    }

    public function sponsor(Request $request)
    {
        return SponsorBonusLog::where('to_user_id', $request->user()->id)
            ->with('fromUser:id,username,name')->latest()->paginate(25);
    }

    public function matching(Request $request)
    {
        return MatchingBonusLog::where('to_user_id', $request->user()->id)
            ->with(['fromUser:id,username,name,rank_id', 'fromUser.rank:id,name'])
            ->latest()->paginate(25);
    }
}
