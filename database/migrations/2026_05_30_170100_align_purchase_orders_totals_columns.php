<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('purchase_orders', 'subtotal')) {
                $table->decimal('subtotal', 12, 2)->default(0)->after('status');
            }
            if (!Schema::hasColumn('purchase_orders', 'tax_amount')) {
                $table->decimal('tax_amount', 12, 2)->default(0)->after('subtotal');
            }
        });

        if (Schema::hasColumn('purchase_orders', 'total_amount')
            && !Schema::hasColumn('purchase_orders', 'total')) {
            Schema::table('purchase_orders', function (Blueprint $table) {
                $table->renameColumn('total_amount', 'total');
            });
        } elseif (!Schema::hasColumn('purchase_orders', 'total')) {
            Schema::table('purchase_orders', function (Blueprint $table) {
                $table->decimal('total', 12, 2)->default(0);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('purchase_orders', 'total')
            && !Schema::hasColumn('purchase_orders', 'total_amount')) {
            Schema::table('purchase_orders', function (Blueprint $table) {
                $table->renameColumn('total', 'total_amount');
            });
        }

        Schema::table('purchase_orders', function (Blueprint $table) {
            if (Schema::hasColumn('purchase_orders', 'tax_amount')) {
                $table->dropColumn('tax_amount');
            }
            if (Schema::hasColumn('purchase_orders', 'subtotal')) {
                $table->dropColumn('subtotal');
            }
        });
    }
};
