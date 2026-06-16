<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Dummy/test accounts: their funding/ROI never pays sponsor or matching
            // bonus to REAL (non-dummy) uplines. Bonuses among dummies still work.
            $table->boolean('is_dummy')->default(false)->after('exclude_from_stats');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_dummy');
        });
    }
};
