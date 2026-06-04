<?php

namespace App\Services;

use App\Models\MatchingBonusLog;
use App\Models\RoiLog;
use App\Models\User;

/**
 * Unlimited-level "Rank Difference" matching bonus.
 *
 * Rank match%: USER 1, FAN 4, SENIOR 8, TEAM LEADER 12, GROUP LEADER 16.
 *
 * Driven by a downline's daily ROI/commission. Walking UP the sponsor chain, the
 * running "floor" starts at the EARNER's own rank%. Each FAN+ upline earns
 *   share = (upline rank%  −  floor)
 * of the commission, then the floor rises to the upline's rank%. A same-or-higher
 * rank yields share <= 0 and cuts the whole leg above.
 *
 * SPECIAL USER RULE: a USER-rank upline always earns its flat 1% from its level 1
 * and level 2 downlines only — even when those downlines are also USER rank. A USER
 * is "transparent" to the rollup: it does not raise the floor nor cut the leg, so a
 * higher-ranked upline further up still earns via the differential.
 *
 * Example  GROUP LEADER(16) ← TEAM LEADER(12) ← SENIOR(8) ← USER(1 earner):
 *   SENIOR 7% (8−1), TEAM LEADER 4% (12−8), GROUP LEADER 4% (16−12).
 * Example  USER ← USER ← USER(earner): each USER upline at level 1 & 2 earns 1%;
 *   a USER at level 3 earns nothing.
 */
class MatchingBonusService
{
    public function __construct(
        protected WalletService $wallets,
        protected SettingsService $settings,
    ) {}

    public function distributeForRoi(RoiLog $roiLog): void
    {
        $earner    = $roiLog->user;
        $roiAmount = $roiLog->amount;
        $percents  = $this->settings->get('match_percents'); // ['USER'=>1,...]
        $maxPct    = max($percents);

        $floor = (float) ($percents[$earner->rankName()] ?? 0);
        $depth = 0;
        $node  = $earner->sponsor;

        while ($node) {
            $depth++;
            $rank      = $node->rankName();
            $uplinePct = (float) ($percents[$rank] ?? 0);

            if ($rank === 'USER') {
                // USER earns its flat % (1%) from level 1 & 2 downlines only,
                // regardless of same-rank. Transparent to the differential rollup.
                if ($depth <= 2 && ! $node->is_frozen) {
                    $this->pay($node, $earner, $roiLog, $roiAmount, $uplinePct, $depth);
                }
                $node = $node->sponsor;
                continue;
            }

            // FAN and above: rank-difference rollup.
            $share = $uplinePct - $floor;
            if ($share <= 0) {
                break; // same or lower rank → cut the whole leg above
            }
            if (! $node->is_frozen) {
                $this->pay($node, $earner, $roiLog, $roiAmount, $share, $depth);
            }
            $floor = $uplinePct;
            if ($floor >= $maxPct) {
                break; // top rank reached, nothing higher
            }
            $node = $node->sponsor;
        }
    }

    /** Credit one matching payout to E-WALLET and log it. */
    protected function pay(User $node, User $earner, RoiLog $roiLog, $roiAmount, float $percent, int $depth): void
    {
        if ($percent <= 0) {
            return;
        }
        $amount = bcdiv(bcmul((string) $roiAmount, (string) $percent, 10), '100', 8);
        if (bccomp($amount, '0', 8) <= 0) {
            return;
        }

        $this->wallets->credit($node, 'E', $amount, 'matching', $roiLog, [
            'from_user_id' => $earner->id, 'percent' => $percent, 'depth' => $depth,
        ]);

        MatchingBonusLog::create([
            'from_user_id'    => $earner->id,
            'to_user_id'      => $node->id,
            'roi_log_id'      => $roiLog->id,
            'upline_rank'     => $node->rankName(),
            'applied_percent' => $percent,
            'roi_amount'      => $roiAmount,
            'amount'          => $amount,
            'depth'           => $depth,
        ]);
    }
}
