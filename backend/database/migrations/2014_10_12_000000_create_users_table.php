<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('username')->unique();
            $table->string('name')->nullable();
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');

            // Networking
            $table->foreignId('sponsor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('rank_id')->nullable()->constrained('ranks')->nullOnDelete();
            $table->string('referral_code')->unique();

            // Money aggregates (cached for fast reads; ledger is source of truth)
            $table->decimal('total_invested', 18, 8)->default(0);
            $table->decimal('total_fund', 18, 8)->default(0); // sum of approved deposits, drives rank

            // Payout
            $table->string('wallet_address')->nullable(); // USDT withdrawal address

            // Flags / security
            $table->boolean('is_admin')->default(false);
            $table->boolean('is_frozen')->default(false);
            $table->string('two_factor_secret')->nullable();
            $table->boolean('two_factor_enabled')->default(false);
            $table->string('last_login_ip')->nullable();
            $table->timestamp('last_login_at')->nullable();

            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();

            $table->index('sponsor_id');
            $table->index('rank_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
