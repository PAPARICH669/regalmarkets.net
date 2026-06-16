<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Dummy/test accounts: still work normally for the member, but are
            // excluded from ADMIN DASHBOARD aggregate totals.
            $table->boolean('exclude_from_stats')->default(false)->after('is_frozen');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('exclude_from_stats');
        });
    }
};
