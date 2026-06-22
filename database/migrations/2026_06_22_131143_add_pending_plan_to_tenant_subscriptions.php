<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tenant_subscriptions', function (Blueprint $table) {
            $table->foreignId('pending_plan_id')->nullable()->constrained('subscription_plans')->nullOnDelete()->after('plan_id');
            $table->string('pending_billing_cycle')->nullable()->after('pending_plan_id');
        });
    }

    public function down(): void
    {
        Schema::table('tenant_subscriptions', function (Blueprint $table) {
            $table->dropForeign(['pending_plan_id']);
            $table->dropColumn(['pending_plan_id', 'pending_billing_cycle']);
        });
    }
};
