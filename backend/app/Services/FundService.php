<?php

namespace App\Services;

use App\Models\InvestmentPackage;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * "Fund" — the single investment-activation action. The member locks capital
 * sitting in their A-WALLET (from an approved deposit, or earnings moved E→A)
 * into a fresh 200% package. This is what starts the daily commission and pays
 * the unilevel sponsor bonus to uplines.
 */
class FundService
{
    public function __construct(
        protected InvestmentService $investment,
        protected SponsorBonusService $sponsor,
        protected RankService $ranks,
        protected SettingsService $settings,
    ) {}

    public function fund(User $user, $amount): InvestmentPackage
    {
        // Members may fund any amount they hold in A-WALLET, from 1 USDT up
        // (so the full deposited value can be activated).
        $min = 1.0;
        if ((float) $amount < $min) {
            throw ValidationException::withMessages(['amount' => "Minimum fund is {$min} USDT."]);
        }
        $amount = number_format((float) $amount, 8, '.', '');

        $package = DB::transaction(function () use ($user, $amount) {
            // Lock A-WALLET capital into a 200% package (throws if A-WALLET short).
            $package = $this->investment->activate($user, $amount, 'fund');

            // Pay unilevel sponsor bonus on the funded amount.
            $this->sponsor->distribute($user, $amount);

            return $package;
        });

        // Recompute ranks (production may have changed).
        $this->ranks->updateAll();

        return $package;
    }
}
