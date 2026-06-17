<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL-only: raw MODIFY ... ENUM has no SQLite equivalent (SQLite stores
        // the column as TEXT and already accepts any value), so skip it there to
        // keep the test suite runnable on SQLite (TEST-01).
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE payments MODIFY method ENUM(
            'cash', 'gcash', 'maya', 'paymongo', 'stripe',
            'card', 'bank_transfer', 'wallet', 'qr', 'wallet_topup'
        ) NOT NULL DEFAULT 'cash'");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE payments MODIFY method ENUM(
            'cash', 'gcash', 'maya', 'paymongo', 'stripe',
            'card', 'bank_transfer', 'wallet', 'qr'
        ) NOT NULL DEFAULT 'cash'");
    }
};
