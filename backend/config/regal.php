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
        'fee_flat'         => 2.0,     // flat fee in USDT per withdrawal (configurable by admin)
        'max_per_day'      => 1,       // max number of withdrawals allowed per day
        'processing_hours' => 72,      // SLA: processed within 72 WORKING hours (Mon–Fri)
        'window_start'     => '09:00', // requests only allowed from 09:00 …
        'window_end'       => '12:00', // … to 12:00 (Asia/Kuala_Lumpur)
    ],

    // Transfer. E-WALLET → A-WALLET (self) is the only E-WALLET transfer allowed,
    // and it is charged a percentage fee. Member-to-member uses A-WALLET only;
    // E-WALLET can never be sent to another member.
    'transfer' => [
        'fee_percent' => 10.0, // % fee on E-WALLET → A-WALLET self transfers
    ],

    // Unilevel sponsor bonus — paid instantly when a downline funds a package, into E-WALLET.
    // Index 0 => level 1.  L1 7%, L2 4%, L3 2%, L4 1%, L5 1%.
    'sponsor_bonus_percents' => [7, 4, 2, 1, 1],

    // Matching bonus override percentages by rank.
    // Rank-difference model: an upline earns (upline% - the highest rank% already
    // covered below it on the path, starting from the ROI earner's own rank%).
    'match_percents' => [
        'USER'         => 1,
        'FAN'          => 3,
        'SENIOR'       => 6,
        'TEAM LEADER'  => 10,
        'GROUP LEADER' => 15,
    ],

    // Monthly Top-5 Sponsor reward (USDT) by leaderboard position. Index 0 => position 1.
    // Ranked by NEW invest brought in by direct downlines during the month.
    'top_sponsor_rewards' => [50, 20, 15, 10, 5],

    // Rank requirements. "produce_rank N" => N DIRECT (level-1) referrals whose rank
    // is at least that rank. (documented in docs/BUSINESS_LOGIC.md).
    'ranks' => [
        // name => [level, match_percent, min_fund, rule]
        'USER'         => ['level' => 1, 'match' => 1,  'min_fund' => 10],
        'FAN'          => ['level' => 2, 'match' => 3,  'min_fund' => 100,  'direct_min_deposit' => 100, 'directs_required' => 3],
        'SENIOR'       => ['level' => 3, 'match' => 6,  'min_fund' => 300,  'produce_rank' => 'FAN',         'produce_count' => 3],
        'TEAM LEADER'  => ['level' => 4, 'match' => 10, 'min_fund' => 1000, 'produce_rank' => 'SENIOR',      'produce_count' => 3],
        'GROUP LEADER' => ['level' => 5, 'match' => 15, 'min_fund' => 5000, 'produce_rank' => 'TEAM LEADER', 'produce_count' => 3],
    ],

    // Daily maintenance window (Asia/Kuala_Lumpur). Login/withdraw/transfer disabled.
    'maintenance' => [
        'start' => '00:00',
        'end'   => '07:01', // login re-opens at 07:01 (after daily commission runs)
    ],

    // Admin USDT (BEP20) wallet address members deposit into.
    'deposit_address' => env('DEPOSIT_ADDRESS', '0x98513096683485c204b2C88b0D8Ae8c524C7646b'),
    'deposit_network' => 'BEP20 (BSC)',

    // Auto-deposit (Cadangan A): verify each deposit on-chain by TX hash and
    // credit automatically. Kept OFF by default — turns on only when
    // DEPOSIT_AUTO_VERIFY=true AND an API key is set. When off, deposits stay
    // fully manual (amount + proof + admin approval), exactly as before.
    'deposit' => [
        'auto_verify'       => env('DEPOSIT_AUTO_VERIFY', false),
        // Public BSC JSON-RPC nodes (no API key). Comma-separated; tried in order
        // with fallback, so one node being down does not stop verification.
        'rpc_urls'          => array_values(array_filter(array_map('trim', explode(',', (string) env('BSC_RPC_URLS',
                                'https://bsc-dataseed.binance.org/,https://bsc-dataseed1.defibit.io/,https://bsc.publicnode.com'))))),
        'usdt_contract'     => env('USDT_BEP20_CONTRACT', '0x55d398326f99059fF775485246999027B3197955'),
        'min_confirmations' => (int) env('DEPOSIT_MIN_CONFIRMATIONS', 15),
        'recency_days'      => (int) env('DEPOSIT_RECENCY_DAYS', 7),          // older TX → manual review
        'review_from_reuse' => (int) env('DEPOSIT_REVIEW_FROM_REUSE', 3),     // same sender on ≥N other accounts → review
    ],

    // Where uploaded payment proofs are stored (private disk, served via controller).
    'proof_disk' => 'local',

    // Coin-swap withdrawals. A member may receive a withdrawal in one of these
    // coins instead of USDT. All are sent on BEP20 (BSC), so every payout address
    // is a 0x… BEP20 address (validated exactly like the USDT wallet_address).
    //
    // Estimate shown to the member = live CoinGecko price × (1 + markup%). The
    // FINAL amount is whatever the admin actually sends (Model A / manual).
    // 'min' and 'fee' are in COIN units. 'dp' is display decimals.
    'coin_swap' => [
        'markup_percent' => 2.0,   // system rate uplift over CoinGecko (admin-tunable via settings)
        'price_ttl'      => 60,    // seconds to cache CoinGecko prices
        'coins' => [
            // USDT is the base (no swap, rate 1). Fee/min mirror the normal withdrawal.
            'USDT' => ['name' => 'Tether',    'coingecko_id' => null,       'network' => 'BEP20', 'dp' => 2, 'min' => 0,       'fee' => 0],
            'BTC'  => ['name' => 'Bitcoin',   'coingecko_id' => 'bitcoin',  'network' => 'BEP20', 'dp' => 8, 'min' => 0.0001,  'fee' => 0.00002],
            'ETH'  => ['name' => 'Ethereum',  'coingecko_id' => 'ethereum', 'network' => 'BEP20', 'dp' => 6, 'min' => 0.001,   'fee' => 0.0003],
            'SOL'  => ['name' => 'Solana',    'coingecko_id' => 'solana',   'network' => 'BEP20', 'dp' => 4, 'min' => 0.02,    'fee' => 0.01],
        ],
    ],
];
