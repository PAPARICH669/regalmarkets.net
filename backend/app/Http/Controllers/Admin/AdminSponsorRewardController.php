<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SponsorReward;
use App\Services\AuditService;
use App\Services\SponsorRewardService;
use Illuminate\Http\Request;

class AdminSponsorRewardController extends Controller
{
    public function __construct(protected SponsorRewardService $service, protected AuditService $audit) {}

    /** Pending rewards first, then recent paid ones. */
    public function index()
    {
        return SponsorReward::with('user:id,username,email')
            ->orderByRaw("FIELD(status,'pending','paid')")
            ->orderBy('period', 'desc')
            ->orderBy('position')
            ->take(60)->get();
    }

    /** Manually generate a period's rewards (defaults to the previous month). */
    public function generate(Request $request)
    {
        $data = $request->validate(['period' => ['nullable', 'regex:/^\d{4}-\d{2}$/']]);
        $period = $data['period'] ?? $this->service->previousPeriod();
        $result = $this->service->generate($period);
        $this->audit->log($request, 'sponsor_rewards.generate', null, $result);

        $note = isset($result['note']) ? " ({$result['note']})" : '';
        return response()->json(['message' => "Generated {$result['created']} reward(s) for {$period}{$note}."]);
    }

    /** Settle one reward: credit E-WALLET + email the member. */
    public function settle(Request $request, SponsorReward $sponsorReward)
    {
        if ($sponsorReward->status === 'paid') {
            return response()->json(['message' => 'This reward was already settled.'], 422);
        }
        $this->service->settle($sponsorReward, $request->user());
        $this->audit->log($request, 'sponsor_rewards.settle', $sponsorReward->user, [
            'period' => $sponsorReward->period, 'position' => $sponsorReward->position, 'amount' => $sponsorReward->amount,
        ]);

        return response()->json(['message' => 'Reward settled — E-WALLET credited and member emailed.']);
    }
}
