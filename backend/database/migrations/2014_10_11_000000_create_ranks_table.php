<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ranks', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();          // USER, FAN, SENIOR, TEAM LEADER, GROUP LEADER
            $table->unsignedTinyInteger('level');        // 1..5
            $table->decimal('match_percent', 6, 2);      // 2/4/8/12/16
            $table->decimal('min_fund', 18, 8)->default(0);
            $table->decimal('direct_min_deposit', 18, 8)->nullable(); // FAN: direct must have >= this deposit
            $table->unsignedInteger('directs_required')->nullable();  // FAN: number of qualifying directs
            $table->string('produce_rank')->nullable();  // SENIOR/TL/GL: which rank must be produced in legs
            $table->unsignedInteger('produce_count')->nullable();     // number of qualifying legs
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ranks');
    }
};
