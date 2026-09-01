<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Coin-swap withdrawals: members may receive a withdrawal in BTC / ETH / SOL
 * (all on BEP20) instead of USDT. USDT withdrawals are unchanged.
 *
 * - users: per-coin payout addresses (all BEP20 / 0x format, like wallet_address).
 * - withdrawals: which coin, the network, the recipient coin address, the system
 *   rate used, the network fee (in coin), the estimated net coin, and the ACTUAL
 *   coin amount the admin sends (source of truth on completion).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('btc_address')->nullable()->after('wallet_address');
            $table->string('eth_address')->nullable()->after('btc_address');
            $table->string('sol_address')->nullable()->after('eth_address');
        });

        Schema::table('withdrawals', function (Blueprint $table) {
            // Existing rows default to USDT/BEP20 so nothing about the current flow changes.
            $table->string('coin', 12)->default('USDT')->after('net_amount');
            $table->string('network', 20)->default('BEP20')->after('coin');
            $table->string('coin_address')->nullable()->after('network');
            $table->decimal('system_rate', 24, 8)->nullable()->after('coin_address');  // USDT per 1 coin (incl. markup)
            $table->decimal('coin_fee', 30, 12)->nullable()->after('system_rate');      // network fee, in coin units
            $table->decimal('coin_amount_est', 30, 12)->nullable()->after('coin_fee');  // estimated net coin at request time
            $table->decimal('coin_amount_actual', 30, 12)->nullable()->after('coin_amount_est'); // admin-entered actual sent
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['btc_address', 'eth_address', 'sol_address']);
        });
        Schema::table('withdrawals', function (Blueprint $table) {
            $table->dropColumn([
                'coin', 'network', 'coin_address', 'system_rate',
                'coin_fee', 'coin_amount_est', 'coin_amount_actual',
            ]);
        });
    }
};
