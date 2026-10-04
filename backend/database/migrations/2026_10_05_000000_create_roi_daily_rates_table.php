<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Monthly-target ROI mode: the pre-generated daily ROI % for each date. The ROI
 * engine reads today's % from here (instead of the flat roi_daily_percent) when
 * roi_mode = 'monthly_target'. Each month's rates fluctuate day-to-day within
 * [min, max] but sum to the monthly target. Already-paid days are preserved.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roi_daily_rates', function (Blueprint $table) {
            $table->id();
            $table->date('rate_date')->unique();
            $table->decimal('percent', 8, 4);     // daily ROI % for that date
            $table->boolean('locked')->default(false); // true once that date has been paid
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roi_daily_rates');
    }
};
