<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // L = LD WALLET (Leader-Distributor credit pool, separate from A/E).
        DB::statement("ALTER TABLE wallets MODIFY COLUMN type ENUM('A','E','L') NOT NULL");
        DB::statement("ALTER TABLE wallet_transactions MODIFY COLUMN wallet_type ENUM('A','E','L') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE wallets MODIFY COLUMN type ENUM('A','E') NOT NULL");
        DB::statement("ALTER TABLE wallet_transactions MODIFY COLUMN wallet_type ENUM('A','E') NOT NULL");
    }
};
