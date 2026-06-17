<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('shifts')) {
            return;
        }

        // Raw ALTER ... MODIFY COLUMN ENUM(...) is MySQL-specific and breaks
        // the SQLite test suite. Branch by driver so test runs (SQLite) skip
        // the no-op enum widening (SQLite treats the column as TEXT anyway).
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE shifts MODIFY COLUMN status ENUM('scheduled','active','completed','absent','late','cancelled') NOT NULL DEFAULT 'scheduled'");
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('shifts')) {
            return;
        }

        if (DB::getDriverName() === 'mysql') {
            DB::statement("UPDATE shifts SET status = 'absent' WHERE status = 'cancelled'");
            DB::statement("ALTER TABLE shifts MODIFY COLUMN status ENUM('scheduled','active','completed','absent','late') NOT NULL DEFAULT 'scheduled'");
        }
    }
};
