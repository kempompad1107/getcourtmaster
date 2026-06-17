<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Hard-reset the audit ledger: the existing rows are in "sessions" units
        // and would be ambiguous after the conversion. The financial/subscription
        // facts are preserved in `payments` and `memberships`.
        DB::table('membership_transactions')->delete();

        // Sessions → minutes (1 session = 60 minutes).
        DB::table('membership_plans')->update([
            'court_credits' => DB::raw('court_credits * 60'),
        ]);
        DB::table('memberships')->update([
            'remaining_credits' => DB::raw('remaining_credits * 60'),
        ]);
    }

    public function down(): void
    {
        DB::table('memberships')->update([
            'remaining_credits' => DB::raw('FLOOR(remaining_credits / 60)'),
        ]);
        DB::table('membership_plans')->update([
            'court_credits' => DB::raw('FLOOR(court_credits / 60)'),
        ]);
        // membership_transactions rows truncated in up() cannot be restored.
    }
};
