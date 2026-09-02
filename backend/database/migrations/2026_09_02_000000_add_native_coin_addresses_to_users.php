<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Native-network payout addresses for coin-swap withdrawals.
 *
 * BTC and SOL can now be received on TWO networks: BEP20 (0x address, stored in
 * the existing btc_address / sol_address) OR the native chain, whose address has
 * a different format:
 *   - btc_native_address : Bitcoin address (bc1…/1…/3…)
 *   - sol_native_address : Solana address (base58)
 * ETH stays BEP20-only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('btc_native_address')->nullable()->after('sol_address');
            $table->string('sol_native_address')->nullable()->after('btc_native_address');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['btc_native_address', 'sol_native_address']);
        });
    }
};
