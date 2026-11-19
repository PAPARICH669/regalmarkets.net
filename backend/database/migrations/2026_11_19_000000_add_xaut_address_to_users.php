<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * XAUT (Tether Gold) withdrawal support: the member's ERC20 (0x) payout address
 * for receiving XAUT. Same EVM address format as the other 0x fields.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('xaut_address')->nullable()->after('sol_native_address');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('xaut_address');
        });
    }
};
