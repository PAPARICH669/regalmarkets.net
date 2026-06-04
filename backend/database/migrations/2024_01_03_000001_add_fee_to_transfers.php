<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transfers', function (Blueprint $table) {
            $table->decimal('fee', 18, 8)->default(0)->after('amount');
            $table->decimal('net_amount', 18, 8)->nullable()->after('fee');
        });
    }

    public function down(): void
    {
        Schema::table('transfers', function (Blueprint $table) {
            $table->dropColumn(['fee', 'net_amount']);
        });
    }
};
