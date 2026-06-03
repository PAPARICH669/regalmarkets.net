<?php

namespace App\Services;

use App\Models\MatchingBonusLog;
use App\Models\RoiLog;
use App\Models\User;

/**
 * Unlimited-level differential rollup matching bonus.
 *
 * Driven by a downline's daily ROI. Walking UP the sponsor chain, each upline
 * earns (their rank match% − the highest match% already paid below them) of the
 * ROI amount:
 *
 *   - Rule 1 (same/lower rank stops): if an upline's % does not exceed what was
 *     already paid, their differential is <= 0 → propagation stops.
 *   - Rule 2 (higher rank gets the balance): an upline of higher rank receives
 *     only the remaining override above the highest already paid.
 *
 * Example  GROUP LEADER(16) ← TEAM LEADER(12) ← SENIOR(8) ← USER(2 earner):
 *   SENIOR 8% (8−0), TEAM LEADER 4% (12−8), GROUP LEADER 4% (16−12).
 * Example  GROUP LEADER ← GROUP LEADER: second GL gets 16−16=0 → STOP.
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
        $percents  = $this->settings->get('match_percents'); // ['USER'=>2,...]

        $paidPercent = 0.0; // highest match% already awarded below the current node
        $depth       = 0;
        $node        = $earner->sponsor;

        while ($node) {
            $depth++;
            $uplinePct = (float) ($percents[$node->rankName()] ?? 0);

            $share = $uplinePct - $paidPercent;
            if ($share <= 0) {
                break; // Rule 1: same or lower rank → stop entirely
            }

            // Frozen uplines forfeit the payout but the rollup continues upward.
            if (! $node->is_frozen) {
                $amount = bcdiv(bcmul($roiAmount, (string) $share, 10), '100', 8);
                if (bccomp($amount, '0', 8) > 0) {
                    $this->wallets->credit(
                        $node, 'E', $amount, 'matching', $roiLog,
                        ['from_user_id' => $earner->id, 'percent' => $share, 'depth' => $depth]
                    );

                    MatchingBonusLog::create([
                        'from_user_id'    => $earner->id,
                        'to_user_id'      => $node->id,
                        'roi_log_id'      => $roiLog->id,
                        'upline_rank'     => $node->rankName(),
                        'applied_percent' => $share,
                        'roi_amount'      => $roiAmount,
                        'amount'          => $amount,
                        'depth'           => $depth,
                    ]);
                }
            }

            $paidPercent = $uplinePct;

            // Once the top override (16% / GROUP LEADER) is paid, nothing higher exists.
            if ($paidPercent >= max($percents)) {
                break;
            }

            $node = $node->sponsor;
        }
    }
}
