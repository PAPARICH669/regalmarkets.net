<?php

namespace Database\Seeders;

use App\Services\SettingsService;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $settings = app(SettingsService::class);

        $settings->set('roi_daily_percent', config('regal.roi.daily_percent'));
        $settings->set('roi_return_multiple', config('regal.roi.return_multiple'));
        $settings->set('min_deposit', config('regal.limits.min_deposit'));
        $settings->set('min_withdrawal', config('regal.limits.min_withdrawal'));
        $settings->set('max_withdrawal_daily', config('regal.limits.max_withdrawal_daily'));
        $settings->set('min_transfer', config('regal.limits.min_transfer'));
        $settings->set('min_reinvest', config('regal.limits.min_reinvest'));
        $settings->set('withdrawal_fee_percent', config('regal.withdrawal.fee_percent'));
        $settings->set('withdrawal_window_start', config('regal.withdrawal.window_start'));
        $settings->set('withdrawal_window_end', config('regal.withdrawal.window_end'));
        $settings->set('transfer_fee_percent', config('regal.transfer.fee_percent'));
        $settings->set('sponsor_bonus_percents', config('regal.sponsor_bonus_percents'));
        $settings->set('match_percents', config('regal.match_percents'));
        $settings->set('maintenance_start', config('regal.maintenance.start'));
        $settings->set('maintenance_end', config('regal.maintenance.end'));
        $settings->set('maintenance_manual', false);
        $settings->set('login_tac_enabled', true);
    }
}
