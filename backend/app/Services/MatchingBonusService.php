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
 * Driven by a downline's daily ROI. Walking UP the sponsor chain, the running
 * "floor" starts at the EARNER's own rank%. Each upline earns
 *   share = (upline rank%  −  floor)
 * of the ROI, then the floor rises to the upline's rank%.
 *
 *   - Cut-off: if an upline's rank% is <= the floor (i.e. a downline below it has
 *     an equal or higher rank), share <= 0 and the ENTIRE leg above is cut (break).
 *   - To keep earning down a leg, an upline must out-rank every member below it.
 *
 * Example  GROUP LEADER(16) ← TEAM LEADER(12) ← SENIOR(8) ← USER(1 earner):
 *   floor starts 1 → SENIOR 7% (8−1), TEAM LEADER 4% (12−8), GROUP LEADER 4% (16−12).
 * Example  Ali GROUP LEADER over a direct leg headed by:
 *   TEAM LEADER → 16−12 = 4% of that whole leg; SENIOR → 8%; FAN → 12%; USER → 15%.
 * Example  GROUP LEADER ← GROUP LEADER: upline GL gets 16−16 = 0 → leg cut (STOP).
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

        // Rank-difference model: the running floor starts at the EARNER's own rank%.
        // Each upline earns (their rank% - this floor); the floor then rises to the
        // upline's rank%. A same-or-higher rank yields share <= 0 and cuts the whole
        // leg above (break).
        $paidPercent = (float) ($percents[$earner->rankName()] ?? 0);
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
