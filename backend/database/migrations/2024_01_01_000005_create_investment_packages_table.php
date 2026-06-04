<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('investment_packages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('deposit_id')->nullable()->constrained('deposits')->nullOnDelete();
            $table->decimal('principal', 18, 8);
            $table->decimal('total_return', 18, 8);   // = principal * return_multiple (200%)
            $table->decimal('total_paid', 18, 8)->default(0);
            $table->decimal('daily_roi_percent', 6, 2);
            $table->decimal('daily_amount', 18, 8);
            $table->enum('status', ['active', 'completed'])->default('active');
            $table->enum('source', ['deposit', 'reinvest', 'fund'])->default('deposit');
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('investment_packages');
    }
};
