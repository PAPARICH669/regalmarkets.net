<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Staff duties — assigned per member. A member with either is a "staff"
            // helper but still a full member (own networking/wallets).
            $table->boolean('can_kyc')->default(false)->after('is_staff');
            $table->boolean('can_cr')->default(false)->after('can_kyc');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['can_kyc', 'can_cr']);
        });
    }
};
