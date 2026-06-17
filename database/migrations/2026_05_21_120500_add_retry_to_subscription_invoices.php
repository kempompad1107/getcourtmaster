<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_invoices', function (Blueprint $table) {
            if (!Schema::hasColumn('subscription_invoices', 'failed_attempts')) {
                $table->unsignedTinyInteger('failed_attempts')->default(0);
            }
            if (!Schema::hasColumn('subscription_invoices', 'next_retry_at')) {
                $table->timestamp('next_retry_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('subscription_invoices', function (Blueprint $table) {
            foreach (['failed_attempts', 'next_retry_at'] as $col) {
                if (Schema::hasColumn('subscription_invoices', $col)) $table->dropColumn($col);
            }
        });
    }
};
