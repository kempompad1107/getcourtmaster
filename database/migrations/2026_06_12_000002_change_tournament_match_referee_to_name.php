<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Referees are often guests rather than system users, so the match referee
 * becomes a free-text name (with staff-name suggestions in the UI) instead of
 * a users FK.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournament_matches', function (Blueprint $table) {
            $table->string('referee_name', 100)->nullable()->after('referee_id');
        });

        DB::statement('
            UPDATE tournament_matches tm
            JOIN users u ON u.id = tm.referee_id
            SET tm.referee_name = u.name
        ');

        Schema::table('tournament_matches', function (Blueprint $table) {
            $table->dropForeign(['referee_id']);
            $table->dropColumn('referee_id');
        });
    }

    public function down(): void
    {
        Schema::table('tournament_matches', function (Blueprint $table) {
            $table->foreignId('referee_id')->nullable()->after('court_id')->constrained('users')->nullOnDelete();
        });

        // Best-effort restore: re-link names that exactly match a user.
        DB::statement('
            UPDATE tournament_matches tm
            JOIN users u ON u.name = tm.referee_name
            SET tm.referee_id = u.id
        ');

        Schema::table('tournament_matches', function (Blueprint $table) {
            $table->dropColumn('referee_name');
        });
    }
};
