<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('from_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('to_user_id')->nullable()->constrained('users')->nullOnDelete(); // null = self E->A
            $table->enum('from_wallet', ['A', 'E']);
            $table->enum('to_wallet', ['A', 'E']);
            $table->decimal('amount', 18, 8);
            $table->enum('type', ['internal_self', 'member_to_member']);
            $table->string('note')->nullable();
            $table->timestamps();

            $table->index('from_user_id');
            $table->index('to_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transfers');
    }
};
