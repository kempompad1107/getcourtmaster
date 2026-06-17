<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_subscriptions', function (Blueprint $table) {
            if (!Schema::hasColumn('tenant_subscriptions', 'renews_at')) {
                $table->date('renews_at')->nullable()->after('starts_at');
            }
            if (!Schema::hasColumn('tenant_subscriptions', 'payment_method_token')) {
                // For Stripe: customer + payment_method ids (encrypted).
                // For PayMongo: payment_method id.
                $table->text('payment_method_token')->nullable();
            }
            if (!Schema::hasColumn('tenant_subscriptions', 'last_charge_at')) {
                $table->timestamp('last_charge_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('tenant_subscriptions', function (Blueprint $table) {
            foreach (['renews_at', 'payment_method_token', 'last_charge_at'] as $col) {
                if (Schema::hasColumn('tenant_subscriptions', $col)) $table->dropColumn($col);
            }
        });
    }
};
