<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Pending LD-transfer confirmation (TAC emailed to the LD).
            $table->string('ld_tac_code', 12)->nullable()->after('is_ld');
            $table->timestamp('ld_tac_expires_at')->nullable()->after('ld_tac_code');
            $table->unsignedBigInteger('ld_tac_member_id')->nullable()->after('ld_tac_expires_at');
            $table->decimal('ld_tac_amount', 18, 8)->nullable()->after('ld_tac_member_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['ld_tac_code', 'ld_tac_expires_at', 'ld_tac_member_id', 'ld_tac_amount']);
        });
    }
};
