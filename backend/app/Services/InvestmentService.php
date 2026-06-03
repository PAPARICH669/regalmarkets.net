<?php

namespace App\Services;

use App\Models\Deposit;
use App\Models\InvestmentPackage;
use App\Models\User;

/**
 * Creates investment packages (200% return). Capital is debited from A-WALLET
 * and "locked" into the package, which then pays daily ROI into E-WALLET.
 */
class InvestmentService
{
    public function __construct(protected WalletService $wallets, protected SettingsService $settings) {}

    public function activate(User $user, $principal, string $source = 'deposit', ?Deposit $deposit = null): InvestmentPackage
    {
        $dailyPercent  = (float) $this->settings->get('roi_daily_percent');
        $multiple      = (float) $this->settings->get('roi_return_multiple');

        $principal    = number_format((float) $principal, 8, '.', '');
        $totalReturn  = bcmul($principal, (string) $multiple, 8);
        $dailyAmount  = bcdiv(bcmul($principal, (string) $dailyPercent, 10), '100', 8);

        // Lock capital out of A-WALLET into the package.
        $package = InvestmentPackage::create([
            'user_id'           => $user->id,
            'deposit_id'        => $deposit?->id,
            'principal'         => $principal,
            'total_return'      => $totalReturn,
            'total_paid'        => 0,
            'daily_roi_percent' => $dailyPercent,
            'daily_amount'      => $dailyAmount,
            'status'            => 'active',
            'source'            => $source,
            'activated_at'      => now(),
        ]);

        $this->wallets->debit($user, 'A', $principal, 'reinvest' === $source ? 'reinvest' : 'deposit', $package, [
            'package_id' => $package->id,
            'lock'       => 'capital',
        ], 'Capital locked into investment package #' . $package->id);

        $user->increment('total_invested', $principal);

        return $package;
    }
}
