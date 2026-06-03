<?php

namespace App\Services;

use App\Models\InvestmentPackage;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Reinvest from E-WALLET (earnings) into a new 200% package. Funds pass through
 * A-WALLET (E → A) before being locked into the package, per the wallet rules.
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

        return DB::transaction(function () use ($user, $amount) {
            // E → A, then activate (which debits A and locks into the package)
            $this->wallets->debit($user, 'E', $amount, 'transfer_out', null, ['flow' => 'reinvest E->A']);
            $this->wallets->credit($user, 'A', $amount, 'transfer_in', null, ['flow' => 'reinvest E->A']);

            return $this->investment->activate($user, $amount, 'reinvest');
        });
    }
}
