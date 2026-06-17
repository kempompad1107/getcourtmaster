<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Player profile fields needed to enforce tournament division gender/age
 * restrictions (previously collected on divisions but unenforceable). Kept as a
 * plain string (not enum) to avoid the MySQL-only MODIFY ENUM migration pitfalls.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('gender', 10)->nullable()->after('phone');
            $table->date('date_of_birth')->nullable()->after('gender');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['gender', 'date_of_birth']);
        });
    }
};
