<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('nickname')->nullable()->after('name');
            $table->string('phone')->nullable()->after('email');

            // Beneficiary / next-of-kin (pewaris)
            $table->string('heir_name')->nullable()->after('phone');
            $table->string('heir_phone')->nullable()->after('heir_name');

            // KYC
            $table->string('id_type')->nullable();          // ic | passport | license
            $table->string('id_number')->nullable()->unique();
            $table->string('kyc_document_path')->nullable();
            $table->string('kyc_status')->default('unsubmitted'); // unsubmitted|pending|verified|rejected
            $table->string('kyc_note')->nullable();
            $table->timestamp('kyc_verified_at')->nullable();
            $table->foreignId('kyc_verified_by')->nullable()->constrained('users')->nullOnDelete();

            // Email verification code
            $table->string('email_verification_code')->nullable();
        });

        // Existing accounts are considered verified so they aren't locked out.
        \Illuminate\Support\Facades\DB::table('users')
            ->whereNull('email_verified_at')->update(['email_verified_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('kyc_verified_by');
            $table->dropColumn([
                'nickname', 'phone', 'heir_name', 'heir_phone', 'id_type', 'id_number',
                'kyc_document_path', 'kyc_status', 'kyc_note', 'kyc_verified_at',
                'email_verification_code',
            ]);
        });
    }
};
