<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->renameColumn('monthly_price', 'price_monthly');
            $table->renameColumn('yearly_price', 'price_yearly');
            $table->integer('max_branches')->nullable()->after('max_staff');
            $table->integer('max_customers')->nullable()->after('max_branches');
        });
    }

    public function down(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->renameColumn('price_monthly', 'monthly_price');
            $table->renameColumn('price_yearly', 'yearly_price');
            $table->dropColumn(['max_branches', 'max_customers']);
        });
    }
};
