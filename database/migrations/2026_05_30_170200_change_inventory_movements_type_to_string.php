<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The enum was too restrictive: the app emits 'in', 'out', 'restock',
        // 'sale', 'return', 'adjustment', 'damage' across several services.
        // A plain string avoids "Data truncated" errors as the vocabulary grows.
        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->string('type', 30)->default('adjustment')->change();
        });
    }

    public function down(): void
    {
        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->enum('type', ['purchase', 'sale', 'adjustment', 'transfer', 'return', 'damage'])
                ->default('purchase')
                ->change();
        });
    }
};
