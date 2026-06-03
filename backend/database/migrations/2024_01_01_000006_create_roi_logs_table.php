<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roi_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('investment_package_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 18, 8);
            $table->date('roi_date');
            $table->timestamps();

            // Idempotency: one payout per package per day
            $table->unique(['investment_package_id', 'roi_date']);
            $table->index(['user_id', 'roi_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roi_logs');
    }
};
