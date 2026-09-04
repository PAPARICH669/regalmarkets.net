<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Matching-rank override. When set, the matching bonus is calculated as if the
 * member were this (usually lower) rank — UNLESS the member genuinely meets the
 * requirements of their displayed rank, in which case the real rank is used.
 * NULL = no override (matching uses the member's actual rank, as before).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('matching_rank_id')->nullable()->after('rank_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('matching_rank_id');
        });
    }
};
