<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE memberships MODIFY COLUMN status ENUM('active','expired','cancelled','frozen','pending','failed') NOT NULL DEFAULT 'pending'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE memberships MODIFY COLUMN status ENUM('active','expired','cancelled','frozen','pending') NOT NULL DEFAULT 'pending'");
    }
};
