<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('purchase_order_items', 'total_cost')
            && !Schema::hasColumn('purchase_order_items', 'line_total')) {
            Schema::table('purchase_order_items', function (Blueprint $table) {
                $table->renameColumn('total_cost', 'line_total');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('purchase_order_items', 'line_total')
            && !Schema::hasColumn('purchase_order_items', 'total_cost')) {
            Schema::table('purchase_order_items', function (Blueprint $table) {
                $table->renameColumn('line_total', 'total_cost');
            });
        }
    }
};
