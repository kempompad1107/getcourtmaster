<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            // Driver name ('paymongo', 'stripe', etc). The method column is the
            // payment instrument shown on receipts; gateway is the processor.
            $table->string('gateway', 32)->nullable()->after('method')->index();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['gateway']);
            $table->dropColumn('gateway');
        });
    }
};
