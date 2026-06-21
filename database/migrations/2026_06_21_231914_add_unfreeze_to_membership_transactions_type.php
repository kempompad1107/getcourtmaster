<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE membership_transactions MODIFY COLUMN type ENUM('purchase','renewal','credit_use','credit_add','freeze','unfreeze','cancel','refund') NOT NULL DEFAULT 'purchase'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE membership_transactions MODIFY COLUMN type ENUM('purchase','renewal','credit_use','credit_add','freeze','cancel','refund') NOT NULL DEFAULT 'purchase'");
    }
};
