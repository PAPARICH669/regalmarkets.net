<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE transfers MODIFY COLUMN type ENUM('internal_self','member_to_member','ld_transfer') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE transfers MODIFY COLUMN type ENUM('internal_self','member_to_member') NOT NULL");
    }
};
