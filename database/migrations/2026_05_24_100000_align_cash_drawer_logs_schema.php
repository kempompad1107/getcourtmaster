<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Earlier code created `cash_drawer_logs` with `running_balance` + `notes` and
 * a narrow ENUM for `action`. The newer code (CashDrawerService) writes
 * `balance_after`, `reason`, and uses string actions like 'in' / 'out' /
 * 'adjust'. The 2026_05_21 migration that defined the new schema short-
 * circuits on `hasTable`, so existing installs never got the new columns.
 *
 * This patch is idempotent — it only renames / retypes if the old form is
 * still present.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cash_drawer_logs')) {
            return;
        }

        // The raw ALTER ... CHANGE / MODIFY statements are MySQL-specific and
        // crash the SQLite test suite. They're also unnecessary on SQLite,
        // which doesn't have ENUM types and stores everything as TEXT —
        // restrict legacy reshape work to MySQL only.
        $isMysql = DB::getDriverName() === 'mysql';

        // Column renames (live MySQL).
        if ($isMysql
            && Schema::hasColumn('cash_drawer_logs', 'running_balance')
            && ! Schema::hasColumn('cash_drawer_logs', 'balance_after')) {
            DB::statement('ALTER TABLE `cash_drawer_logs` CHANGE `running_balance` `balance_after` DECIMAL(12,2) NOT NULL DEFAULT 0');
        }

        if ($isMysql
            && Schema::hasColumn('cash_drawer_logs', 'notes')
            && ! Schema::hasColumn('cash_drawer_logs', 'reason')) {
            DB::statement('ALTER TABLE `cash_drawer_logs` CHANGE `notes` `reason` VARCHAR(255) NULL');
        }

        // Replace the narrow ENUM action with a free-form string so the
        // service's new vocabulary ('in', 'out', 'adjust') and any future
        // values inserts cleanly. Map old values onto the new scheme first
        // so historical balance reads stay consistent.
        if ($isMysql) {
            $isEnum = DB::selectOne("
                SELECT COLUMN_TYPE
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'cash_drawer_logs'
                  AND COLUMN_NAME = 'action'
            ");

            if ($isEnum && str_starts_with(strtolower($isEnum->COLUMN_TYPE ?? ''), 'enum')) {
                // Normalise legacy values inside the ENUM space before we widen.
                DB::statement("UPDATE `cash_drawer_logs` SET `action` = 'in'  WHERE `action` IN ('cash_in', 'sale')");
                DB::statement("UPDATE `cash_drawer_logs` SET `action` = 'out' WHERE `action` IN ('cash_out', 'refund')");
                DB::statement('ALTER TABLE `cash_drawer_logs` MODIFY `action` VARCHAR(40) NOT NULL');
            }
        }

        // Make sure balance_after exists (covers a worst-case where the table
        // was created from scratch with neither column).
        if (! Schema::hasColumn('cash_drawer_logs', 'balance_after')) {
            Schema::table('cash_drawer_logs', function (Blueprint $table) {
                $table->decimal('balance_after', 12, 2)->default(0)->after('amount');
            });
        }
        if (! Schema::hasColumn('cash_drawer_logs', 'reason')) {
            Schema::table('cash_drawer_logs', function (Blueprint $table) {
                $table->string('reason')->nullable()->after('balance_after');
            });
        }
    }

    public function down(): void
    {
        // Intentionally non-reversible: rolling back to the legacy ENUM
        // would lose any rows whose action is outside that set and
        // mangle the column names again. Restore from backup instead.
    }
};
