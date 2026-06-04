<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE investment_packages MODIFY source ENUM('deposit','reinvest','fund') NOT NULL DEFAULT 'deposit'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE investment_packages MODIFY source ENUM('deposit','reinvest') NOT NULL DEFAULT 'deposit'");
        }
    }
};
