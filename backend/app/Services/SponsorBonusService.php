<?php

namespace App\Services;

use App\Models\Deposit;
use App\Models\SponsorBonusLog;
use App\Models\User;

/**
 * Unilevel sponsor bonus, paid INSTANTLY on deposit activation into E-WALLET.
 * Level 1..5 = [10, 5, 3, 2, 1]% of the activated amount (configurable).
 */
class SponsorBonusService
{
    public function __construct(
        protected WalletService $wallets,
        protected SettingsService $settings,
    ) {}

    public function distribute(User $member, $amount, ?Deposit $deposit = null): void
    {
        $percents = $this->settings->get('sponsor_bonus_percents'); // [10,5,3,2,1]
        $node     = $member->sponsor;
        $level    = 0;

        while ($node && $level < count($percents)) {
            $percent = (float) $percents[$level];
            $level++;

            if (! $node->is_frozen && $percent > 0) {
                $bonus = bcdiv(bcmul((string) $amount, (string) $percent, 10), '100', 8);
                if (bccomp($bonus, '0', 8) > 0) {
                    $this->wallets->credit(
                        $node, 'E', $bonus, 'sponsor', $deposit,
                        ['from_user_id' => $member->id, 'level' => $level, 'percent' => $percent]
                    );

                    SponsorBonusLog::create([
                        'from_user_id' => $member->id,
                        'to_user_id'   => $node->id,
                        'deposit_id'   => $deposit?->id,
                        'level'        => $level,
                        'percent'      => $percent,
                        'amount'       => $bonus,
                    ]);
                }
            }

            $node = $node->sponsor;
        }
    }
}
