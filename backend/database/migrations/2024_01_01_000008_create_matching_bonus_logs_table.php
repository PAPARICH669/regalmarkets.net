<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('matching_bonus_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('from_user_id')->constrained('users')->cascadeOnDelete(); // ROI earner downline
            $table->foreignId('to_user_id')->constrained('users')->cascadeOnDelete();    // upline who earned override
            $table->foreignId('roi_log_id')->nullable()->constrained('roi_logs')->nullOnDelete();
            $table->string('upline_rank');
            $table->decimal('applied_percent', 6, 2); // differential percent actually applied
            $table->decimal('roi_amount', 18, 8);      // base ROI the override was computed from
            $table->decimal('amount', 18, 8);
            $table->unsignedInteger('depth'); // hops up the tree
            $table->timestamps();

            $table->index('to_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('matching_bonus_logs');
    }
};
