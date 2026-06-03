<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sponsor_bonus_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('from_user_id')->constrained('users')->cascadeOnDelete(); // who deposited
            $table->foreignId('to_user_id')->constrained('users')->cascadeOnDelete();   // upline who earned
            $table->foreignId('deposit_id')->nullable()->constrained('deposits')->nullOnDelete();
            $table->unsignedTinyInteger('level'); // 1..5
            $table->decimal('percent', 6, 2);
            $table->decimal('amount', 18, 8);
            $table->timestamps();

            $table->index('to_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sponsor_bonus_logs');
    }
};
