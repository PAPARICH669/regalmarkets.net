<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sponsor_rewards', function (Blueprint $table) {
            $table->id();
            $table->string('period', 7);                  // 'YYYY-MM' the reward is for
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('position');       // 1..5
            $table->decimal('score', 18, 8)->default(0);   // monthly new invest from direct downlines
            $table->decimal('amount', 18, 8);              // reward USDT
            $table->string('status')->default('pending');  // pending | paid
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->unique(['period', 'position']);
            $table->index(['status']);
            $table->index(['user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sponsor_rewards');
    }
};
