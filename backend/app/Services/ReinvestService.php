<?php

namespace App\Services;

use App\Models\InvestmentPackage;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Reinvest into a new 200% package using A-WALLET (capital) funds ONLY.
 * Earnings in E-WALLET must first be moved to A-WALLET via a self transfer
 * (E → A, subject to the transfer fee) before they can be reinvested.
 */
class ReinvestService
{
    public function __construct(
        protected WalletService $wallets,
        protected InvestmentService $investment,
        protected SettingsService $settings,
    ) {}

    public function reinvest(User $user, $amount): InvestmentPackage
    {
        $min = (float) $this->settings->get('min_reinvest');
        if ((float) $amount < $min) {
            throw ValidationException::withMessages(['amount' => "Minimum reinvest is {$min} USDT."]);
        }
        $amount = number_format((float) $amount, 8, '.', '');

        // activate() debits A-WALLET and locks the capital into the new package.
        return DB::transaction(fn () => $this->investment->activate($user, $amount, 'reinvest'));
    }
}
