<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('login_tac_code', 12)->nullable()->after('email_verification_code');
            $table->timestamp('login_tac_expires_at')->nullable()->after('login_tac_code');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['login_tac_code', 'login_tac_expires_at']);
        });
    }
};
