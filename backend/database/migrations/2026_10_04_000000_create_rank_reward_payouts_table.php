<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rank Rewards Campaign — tracks which rank reward each member has already been
 * paid, so a reward is credited at most ONCE per member per rank (idempotent).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rank_reward_payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rank_id')->constrained('ranks');
            $table->decimal('amount', 18, 8);
            $table->timestamp('paid_at');
            $table->timestamps();
            $table->unique(['user_id', 'rank_id']); // one reward per member per rank
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rank_reward_payouts');
    }
};
