<?php

/*
|--------------------------------------------------------------------------
| Regal Markets — Business Configuration Defaults
|--------------------------------------------------------------------------
|
| These are the fallback defaults for the platform. Anything that an admin
| can change at runtime is also stored in the `settings` table and read via
| SettingsService (DB value wins over these defaults). Keep this file as the
| single source of truth for "factory" values + seeding.
|
| Currency: USDT. All monetary values are decimal(18,8). Timezone is set in
| config/app.php to Asia/Kuala_Lumpur.
*/

return [

    'currency' => 'USDT',

    // ROI / investment package
    'roi' => [
        'daily_percent'   => 1.0,   // 1% of principal per day
        'return_multiple' => 2.0,   // 200% total return (deposit x2 then stops)
    ],

    // Deposit / withdrawal / transfer limits
    'limits' => [
        'min_deposit'           => 10,
        'min_withdrawal'        => 10,
        'max_withdrawal_daily'  => 1000,
        'min_transfer'          => 10,
        'min_reinvest'          => 10,
    ],

    // Withdrawal
    'withdrawal' => [
        'fee_flat'         => 2.0,   // flat fee in USDT per withdrawal (configurable by admin)
        'max_per_day'      => 1,     // max number of withdrawals allowed per day
        'processing_hours' => 72,    // informational SLA shown to users
    ],

    // Unilevel sponsor bonus — paid instantly on deposit activation, into E-WALLET.
    // Index 0 => level 1.
    'sponsor_bonus_percents' => [10, 5, 3, 2, 1],

    // Matching bonus override percentages by rank.
    // Rank-difference model: an upline earns (upline% - the highest rank% already
    // covered below it on the path, starting from the ROI earner's own rank%).
    'match_percents' => [
        'USER'         => 1,
        'FAN'          => 4,
        'SENIOR'       => 8,
        'TEAM LEADER'  => 12,
        'GROUP LEADER' => 16,
    ],

    // Rank requirements. "produce_rank N" => N DIRECT (level-1) referrals whose rank
    // is at least that rank. (documented in docs/BUSINESS_LOGIC.md).
    'ranks' => [
        // name => [level, match_percent, min_fund, rule]
        'USER'         => ['level' => 1, 'match' => 1,  'min_fund' => 10],
        'FAN'          => ['level' => 2, 'match' => 4,  'min_fund' => 100,  'direct_min_deposit' => 100, 'directs_required' => 3],
        'SENIOR'       => ['level' => 3, 'match' => 8,  'min_fund' => 300,  'produce_rank' => 'FAN',         'produce_count' => 3],
        'TEAM LEADER'  => ['level' => 4, 'match' => 12, 'min_fund' => 1000, 'produce_rank' => 'SENIOR',      'produce_count' => 3],
        'GROUP LEADER' => ['level' => 5, 'match' => 16, 'min_fund' => 5000, 'produce_rank' => 'TEAM LEADER', 'produce_count' => 3],
    ],

    // Daily maintenance window (Asia/Kuala_Lumpur). Login/withdraw/transfer disabled.
    'maintenance' => [
        'start' => '00:00',
        'end'   => '07:00', // active again at 07:00 (window is 00:00–06:59)
    ],

    // Admin USDT (BEP20) wallet address members deposit into.
    'deposit_address' => env('DEPOSIT_ADDRESS', '0x98513096683485c204b2C88b0D8Ae8c524C7646b'),
    'deposit_network' => 'BEP20 (BSC)',

    // Where uploaded payment proofs are stored (private disk, served via controller).
    'proof_disk' => 'local',
];
