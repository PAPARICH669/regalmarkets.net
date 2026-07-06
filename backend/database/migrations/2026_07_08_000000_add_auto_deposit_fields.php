<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Auto-deposit (Cadangan A): on-chain USDT (BEP20) verification by TX hash.
 * Adds the normalized, UNIQUE tx_hash (the double-claim guard), the on-chain
 * facts we read back (from address, amount is stored in the existing `amount`
 * column, block, confirmations), and two new lifecycle states:
 *   verifying — found/awaiting confirmations; the scheduler re-checks it
 *   review    — matched but flagged (too old / unusual sender) → manual check
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE deposits MODIFY COLUMN status ENUM('pending','verifying','review','approved','rejected') NOT NULL DEFAULT 'pending'");

        Schema::table('deposits', function (Blueprint $table) {
            $table->string('tx_hash', 66)->nullable()->unique()->after('txid'); // 0x + 64 hex
            $table->string('from_address', 42)->nullable()->after('tx_hash');
            $table->unsignedBigInteger('block_number')->nullable()->after('from_address');
            $table->unsignedInteger('confirmations')->default(0)->after('block_number');
            $table->timestamp('verified_at')->nullable()->after('approved_at');
        });
    }

    public function down(): void
    {
        Schema::table('deposits', function (Blueprint $table) {
            $table->dropUnique(['tx_hash']);
            $table->dropColumn(['tx_hash', 'from_address', 'block_number', 'confirmations', 'verified_at']);
        });

        DB::statement("ALTER TABLE deposits MODIFY COLUMN status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending'");
    }
};
