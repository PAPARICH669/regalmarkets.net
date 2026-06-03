<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Append-only ledger. Every balance mutation writes exactly one row here.
        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('wallet_type', ['A', 'E']);
            $table->string('type'); // deposit|roi|sponsor|matching|transfer_in|transfer_out|withdrawal|withdrawal_refund|reinvest|admin_adjust
            $table->enum('direction', ['credit', 'debit']);
            $table->decimal('amount', 18, 8);
            $table->decimal('balance_after', 18, 8);
            $table->nullableMorphs('reference'); // reference_type + reference_id
            $table->json('meta')->nullable();
            $table->string('note')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'wallet_type']);
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_transactions');
    }
};
