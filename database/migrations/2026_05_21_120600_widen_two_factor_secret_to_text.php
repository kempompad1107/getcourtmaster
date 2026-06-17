<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Encrypted 2FA secrets are far longer than the 255 char varchar default.
     * Widen the column (and recovery codes, for the same reason) to TEXT.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('two_factor_secret')->nullable()->change();

            if (Schema::hasColumn('users', 'two_factor_recovery_codes')) {
                $table->text('two_factor_recovery_codes')->nullable()->change();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('two_factor_secret')->nullable()->change();

            if (Schema::hasColumn('users', 'two_factor_recovery_codes')) {
                $table->string('two_factor_recovery_codes')->nullable()->change();
            }
        });
    }
};
