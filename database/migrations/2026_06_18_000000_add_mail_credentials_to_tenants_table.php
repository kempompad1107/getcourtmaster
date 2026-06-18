<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            // Whole JSON blob is encrypted at rest (SMTP password lives inside).
            $table->text('mail_credentials')->nullable()->after('payment_credentials');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('mail_credentials');
        });
    }
};
