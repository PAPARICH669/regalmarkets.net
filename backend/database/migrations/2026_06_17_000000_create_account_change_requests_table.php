<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_change_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('field');                 // 'email' | 'wallet_address'
            $table->text('old_value')->nullable();
            $table->text('new_value');
            $table->string('status')->default('pending_tac'); // pending_tac | pending | approved | rejected
            $table->string('tac_code', 12)->nullable();
            $table->timestamp('tac_expires_at')->nullable();
            $table->timestamp('tac_verified_at')->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->string('note', 255)->nullable();
            $table->timestamps();
            $table->index(['status', 'field']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_change_requests');
    }
};
