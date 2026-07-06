<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('kyc_country', 2)->nullable()->after('id_number');   // ISO-2 country of the ID
            $table->string('kyc_selfie_path')->nullable()->after('kyc_document_hash');
            $table->string('kyc_selfie_hash', 64)->nullable()->after('kyc_selfie_path');
            $table->index('kyc_selfie_hash');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['kyc_selfie_hash']);
            $table->dropColumn(['kyc_country', 'kyc_selfie_path', 'kyc_selfie_hash']);
        });
    }
};
